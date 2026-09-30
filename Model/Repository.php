<?php

declare(strict_types=1);

namespace RocketWeb\ShoppingFeedMigration\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;

class Repository
{
    public const RECEIPT = 'rw_feed_migration_receipt';
    public function __construct(private ResourceConnection $resource)
    {
    }

    public function db(): AdapterInterface
    {
        return $this->resource->getConnection();
    }
    public function table(string $name): string
    {
        return $this->resource->getTableName($name);
    }

    public function candidates(): array
    {
        if (!$this->db()->isTableExists($this->table('rw_shoppingfeeds_feed'))) {
            return [];
        }
        return $this->db()->fetchAll($this->db()->select()->from($this->table('rw_shoppingfeeds_feed'), ['id', 'name', 'type', 'store_id'])->order('id'));
    }

    public function receipts(): array
    {
        return $this->db()->fetchAll($this->db()->select()->from($this->table(self::RECEIPT), ['source_id', 'target_id', 'state', 'created_at'])->order('source_id'));
    }

    public function source(int $id, bool $lock = false): array
    {
        $data = $this->snapshot('rw_shoppingfeeds_', $id, $lock);
        $data['system'] = $this->rows('core_config_data', ['path LIKE ?' => 'shoppingfeeds/%'], $lock, 'config_id');
        return $data;
    }

    public function target(int $id, bool $lock = false): array
    {
        return $this->snapshot('mageos_shopping_feed_', $id, $lock);
    }

    private function snapshot(string $prefix, int $id, bool $lock): array
    {
        $feeds = $this->rows($prefix . 'feed', ['id = ?' => $id], $lock);
        if (count($feeds) !== 1) {
            throw new \DomainException('The selected feed no longer exists.');
        }
        $result = ['feed' => $feeds[0]];
        foreach (['config' => 'feed_config', 'schedules' => 'feed_schedule', 'uploads' => 'feed_upload'] as $key => $suffix) {
            $result[$key] = $this->rows($prefix . $suffix, ['feed_id = ?' => $id], $lock);
        }
        return $result;
    }

    public function rows(string $name, array $where, bool $lock = false, string $order = 'id'): array
    {
        $select = $this->db()->select()->from($this->table($name))->order($order);
        foreach ($where as $condition => $value) {
            $select->where($condition, $value);
        }
        if ($lock) {
            $select->forUpdate(true);
        }
        return $this->db()->fetchAll($select);
    }

    public function receipt(int $sourceId, bool $lock = false): ?array
    {
        return $this->rows(self::RECEIPT, ['source_id = ?' => $sourceId], $lock, 'source_id')[0] ?? null;
    }

    public function import(array $plan): int
    {
        $feed = $plan['source']['feed'];
        unset($feed['id']);
        $feed['status'] = 0;
        $feed['use_microdata'] = 0;
        $feed['messages'] = '[]';
        $this->db()->insert($this->table('mageos_shopping_feed_feed'), $feed);
        $id = (int)$this->db()->lastInsertId($this->table('mageos_shopping_feed_feed'));
        foreach ($plan['config'] as $path => $value) {
            $this->db()->insert($this->table('mageos_shopping_feed_feed_config'), ['feed_id' => $id, 'path' => $path, 'value' => $value]);
        }
        return $id;
    }

    public function saveReceipt(array $row): void
    {
        $this->db()->insert($this->table(self::RECEIPT), $row);
    }

    public function setState(int $sourceId, string $state): void
    {
        $this->db()->update($this->table(self::RECEIPT), ['state' => $state], ['source_id = ?' => $sourceId]);
    }

    public function assertIdle(string $prefix, int $id): void
    {
        foreach (['feed_queue', 'process'] as $suffix) {
            $where = ['feed_id = ?' => $id];
            if ($suffix === 'process') {
                // Completed product checkpoints remain after a successful run.
                $where['status = ?'] = 0;
            }
            if ($this->rows($prefix . $suffix, $where, true)) {
                throw new \DomainException('Feed has queued or processing work. Drain it before continuing.');
            }
        }
    }

    public function activate(int $id, array $snapshot): void
    {
        foreach (['schedules' => 'feed_schedule', 'uploads' => 'feed_upload'] as $key => $suffix) {
            foreach ($snapshot[$key] as $row) {
                unset($row['id']);
                $row['feed_id'] = $id;
                if ($key === 'schedules') {
                    unset($row['processed_at']);
                }
                $this->db()->insert($this->table('mageos_shopping_feed_' . $suffix), $row);
            }
        }
        $this->db()->update($this->table('mageos_shopping_feed_feed'), ['status' => 1], ['id = ?' => $id]);
    }

    public function removeImportedFeed(int $id): void
    {
        // The destination module owns cascading deletion of its children.
        $this->db()->delete($this->table('mageos_shopping_feed_feed'), ['id = ?' => $id]);
    }
}
