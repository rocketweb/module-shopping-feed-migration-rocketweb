<?php

declare(strict_types=1);

namespace RocketWeb\ShoppingFeedMigration\Model;

use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Lock\LockManagerInterface;

class Migration
{
    public function __construct(
        private Repository $repository,
        private Planner $planner,
        private EncryptorInterface $encryptor,
        private LockManagerInterface $locks,
        private DeploymentConfig $deploymentConfig
    ) {
    }

    public function preview(int $sourceId): array
    {
        $plan = $this->planner->build($this->repository->source($sourceId));
        $report = $this->planner->report($plan);
        $report['token'] = $this->digest($plan);
        $report['receipt'] = $this->publicReceipt($this->repository->receipt($sourceId));
        return $report;
    }

    public function import(int $sourceId, string $token, string $backupReference): array
    {
        if (trim($backupReference) === '' || strlen($backupReference) > 255 || preg_match('/[\x00-\x1f]/', $backupReference)) {
            throw new \DomainException('Supply a backup reference (1 to 255 characters, no credentials).');
        }
        return $this->transaction(function () use ($sourceId, $token, $backupReference): array {
            if ($this->repository->receipt($sourceId, true)) {
                throw new \DomainException('This source already has a migration receipt. Repeated imports are blocked.');
            }
            $plan = $this->planner->build($this->repository->source($sourceId, true));
            $this->matchToken($token, $this->digest($plan));
            $targetId = $this->repository->import($plan);
            $row = ['source_id' => $sourceId, 'target_id' => $targetId, 'state' => 'imported',
                'target_hash' => $this->digest($this->repository->target($targetId, true)),
                'payload' => $this->encryptor->encrypt(json_encode($plan, JSON_THROW_ON_ERROR)),
                'backup_reference' => $backupReference];
            $this->repository->saveReceipt($row);
            return $this->publicReceipt($row);
        });
    }

    /** Preview is required again immediately before activation or rollback. */
    public function review(int $sourceId, string $action): array
    {
        return $this->transaction(function () use ($sourceId, $action): array {
            [$receipt, $target, $source] = $this->transitionContext($sourceId, $action);
            return ['action' => $action, 'source_id' => $sourceId, 'target_id' => (int)$receipt['target_id'],
                'token' => $this->digest([$action, $receipt, $target, $source]),
                'effect' => $action === 'activate'
                    ? 'Restore held schedules and upload destinations; enable the imported feed. Microdata stays off. Confirm output and fetch URL cutover first.'
                    : 'Delete only the unchanged imported feed and its configuration; retain its encrypted receipt.'];
        });
    }

    public function transition(int $sourceId, string $action, string $token): array
    {
        return $this->transaction(function () use ($sourceId, $action, $token): array {
            [$receipt, $target, $source] = $this->transitionContext($sourceId, $action);
            $this->matchToken($token, $this->digest([$action, $receipt, $target, $source]));
            if ($action === 'activate') {
                $saved = json_decode($this->encryptor->decrypt($receipt['payload']), true, 512, JSON_THROW_ON_ERROR);
                $this->repository->activate((int)$receipt['target_id'], $saved['source']);
                $state = 'activated';
            } else {
                $this->repository->removeImportedFeed((int)$receipt['target_id']);
                $state = 'rolled_back';
            }
            $this->repository->setState($sourceId, $state);
            $receipt['state'] = $state;
            return $this->publicReceipt($receipt);
        });
    }

    private function transitionContext(int $sourceId, string $action): array
    {
        if (!in_array($action, ['activate', 'rollback'], true)) {
            throw new \DomainException('Unsupported migration action.');
        }
        $receipt = $this->repository->receipt($sourceId, true);
        if (!$receipt || $receipt['state'] !== 'imported') {
            throw new \DomainException('Only a prepared import may be activated or rolled back.');
        }
        $target = $this->repository->target((int)$receipt['target_id'], true);
        $this->repository->assertIdle('mageos_shopping_feed_', (int)$receipt['target_id']);
        if ($target['schedules'] || $target['uploads'] || (int)$target['feed']['use_microdata'] !== 0) {
            throw new \DomainException('Imported feed has been activated or edited. Resolve it manually.');
        }
        $source = [];
        if ($action === 'rollback') {
            if (!hash_equals($receipt['target_hash'], $this->digest($target))) {
                throw new \DomainException('Imported feed changed after migration. Automatic rollback would discard changes.');
            }
        } else {
            $source = $this->repository->source($sourceId, true);
            if ((int)$source['feed']['status'] !== 0) {
                throw new \DomainException('Disable the original feed in its editor before activating the replacement. Keep the legacy module enabled.');
            }
            $this->repository->assertIdle('rw_shoppingfeeds_', $sourceId);
            $saved = json_decode($this->encryptor->decrypt($receipt['payload']), true, 512, JSON_THROW_ON_ERROR);
            // Status/messages/timestamps change during normal operation; business settings must not drift.
            $current = $source;
            $original = $saved['source'];
            foreach (['status', 'messages', 'updated_at'] as $key) {
                unset($current['feed'][$key], $original['feed'][$key]);
            }
            foreach (['current', 'original'] as $which) {
                foreach (${$which}['schedules'] as &$schedule) {
                    unset($schedule['processed_at']);
                }
                unset($schedule);
            }
            if ($this->digest($current) !== $this->digest($original)) {
                throw new \DomainException('Legacy configuration changed after import. Review it before cutover.');
            }
            if (!in_array((int)$target['feed']['status'], [0, 4], true)) {
                throw new \DomainException('Imported feed must be disabled or successfully previewed before activation.');
            }
        }
        return [$receipt, $target, $source];
    }

    private function transaction(callable $operation): array
    {
        if (!$this->locks->lock('rocketweb_feed_migration', 0)) {
            throw new \DomainException('Another migration is running. Try again after it finishes.');
        }
        try {
            $this->repository->db()->beginTransaction();
            try {
                $result = $operation();
                $this->repository->db()->commit();
                return $result;
            } catch (\Throwable $e) {
                $this->repository->db()->rollBack();
                throw $e;
            }
        } finally {
            $this->locks->unlock('rocketweb_feed_migration');
        }
    }

    private function digest(array $data): string
    {
        // Keyed hashes prevent preview tokens from being used to guess saved credentials.
        $key = (string)$this->deploymentConfig->get('crypt/key');
        if ($key === '') {
            throw new \DomainException('The installation encryption key is unavailable.');
        }
        return hash_hmac('sha256', json_encode($data, JSON_THROW_ON_ERROR), $key);
    }

    private function matchToken(string $provided, string $current): void
    {
        if ($provided === '' || !hash_equals($current, $provided)) {
            throw new \DomainException('The preview is stale or invalid. Review a fresh preview before applying.');
        }
    }

    private function publicReceipt(?array $row): ?array
    {
        return $row ? array_intersect_key($row, array_flip(['source_id', 'target_id', 'state', 'created_at'])) : null;
    }
}
