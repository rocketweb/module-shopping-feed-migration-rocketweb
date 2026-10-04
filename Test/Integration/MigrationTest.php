<?php

declare(strict_types=1);

namespace RocketWeb\ShoppingFeedMigration\Test\Integration;

use PHPUnit\Framework\TestCase;
use RocketWeb\ShoppingFeedMigration\Model\{ConfigCodec, Definitions, Migration, Planner, Repository};
use Magento\Framework\App\{DeploymentConfig, ResourceConnection};
use Magento\Framework\DB\Adapter\Pdo\Mysql;
use Magento\Framework\DB\{SelectFactory, Logger\Quiet};
use Magento\Framework\DB\Select\SelectRenderer;
use Magento\Framework\Encryption\{Encryptor, KeyValidator};
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Math\Random;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Setup\Declaration\Schema\Dto\Factories\Table as TableFactory;
use RocketWeb\ShoppingFeedMigration\Test\Unit\PlannerTest;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class MigrationTest extends TestCase
{
    private Mysql $db;
    private Repository $repository;
    private Migration $migration;
    private Encryptor $encryptor;
    private const PREFIX = 'rw_migration_test_';

    protected function setUp(): void
    {
        if (getenv('MIGRATION_TEST_DB') !== 'feed_migration_test') {
            self::markTestSkipped('Set MIGRATION_TEST_DB=feed_migration_test for a disposable local database.');
        }
        $port = getenv('MIGRATION_TEST_PORT') ?: '13389';
        if (!ctype_digit($port) || (int)$port < 1024 || (int)$port > 65535) {
            throw new \RuntimeException('MIGRATION_TEST_PORT must be a local unprivileged port.');
        }
        $renderers = [];
        foreach (['Distinct', 'Columns', 'Union', 'From', 'Where', 'Group', 'Having', 'Order', 'Limit', 'ForUpdate'] as $i => $name) {
            $class = 'Magento\\Framework\\DB\\Select\\' . $name . 'Renderer';
            $part = ['Limit' => 'limitcount', 'ForUpdate' => 'forupdate'][$name] ?? strtolower($name);
            $renderers[] = ['renderer' => new $class(new \Magento\Framework\DB\Platform\Quote()), 'sort' => ($i + 1) * 100, 'part' => $part];
        }
        $this->db = new Mysql(
            new \Magento\Framework\Stdlib\StringUtils(),
            new \Magento\Framework\Stdlib\DateTime(),
            new Quiet(),
            new SelectFactory(new SelectRenderer($renderers)),
            ['host' => '127.0.0.1:' . $port, 'dbname' => 'feed_migration_test', 'username' => 'root', 'password' => ''],
            new Json(),
            $this->createMock(TableFactory::class)
        );
        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($this->db);
        $resource->method('getTableName')->willReturnCallback(static fn($name) => self::PREFIX . $name);
        $this->repository = new Repository($resource);
        $config = $this->createMock(DeploymentConfig::class);
        $config->method('get')->willReturn(bin2hex(random_bytes(16)));
        $this->encryptor = new Encryptor(new Random(), $config, $this->createMock(KeyValidator::class));
        $definitions = $this->createMock(Definitions::class);
        $definitions->method('get')->willReturn([PlannerTest::definition(), PlannerTest::definition()]);
        $locks = $this->createMock(LockManagerInterface::class);
        $locks->method('lock')->willReturn(true);
        $this->migration = new Migration($this->repository, new Planner($definitions, new ConfigCodec(), $this->encryptor), $this->encryptor, $locks, $config);
        $this->schema();
        $this->fixtures();
    }

    private function schema(): void
    {
        // Only disposable, prefixed test tables are ever dropped.
        $this->db->query('SET FOREIGN_KEY_CHECKS=0');
        foreach ($this->db->fetchCol('SHOW TABLES') as $table) {
            if (str_starts_with($table, self::PREFIX)) {
                $this->db->query('DROP TABLE ' . $this->db->quoteIdentifier($table));
            }
        }
        $this->db->query('SET FOREIGN_KEY_CHECKS=1');
        foreach (['rw_shoppingfeeds_', 'mageos_shopping_feed_'] as $prefix) {
            $feed = self::PREFIX . $prefix . 'feed';
            $this->db->query("CREATE TABLE `$feed` (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, store_id INT NOT NULL, name VARCHAR(255), type VARCHAR(100), status INT NOT NULL DEFAULT 1, use_microdata INT NOT NULL DEFAULT 0, messages TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
            foreach (
                [
                'feed_config' => 'path VARCHAR(255), value MEDIUMTEXT NOT NULL, UNIQUE KEY feed_path(feed_id,path)',
                'feed_schedule' => 'start_at INT NOT NULL, processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, batch_mode INT DEFAULT 0, batch_limit INT NOT NULL',
                'feed_upload' => 'username VARCHAR(255), password TEXT, host VARCHAR(255), port INT NOT NULL, path VARCHAR(255), mode VARCHAR(255), gzip INT NOT NULL',
                'feed_queue' => 'message TEXT', 'process' => 'status INT',
                ] as $suffix => $columns
            ) {
                $table = self::PREFIX . $prefix . $suffix;
                $this->db->query("CREATE TABLE `$table` (id INT AUTO_INCREMENT PRIMARY KEY, feed_id INT UNSIGNED NOT NULL, $columns, FOREIGN KEY(feed_id) REFERENCES `$feed`(id) ON DELETE CASCADE) ENGINE=InnoDB");
            }
        }
        $this->db->query('CREATE TABLE `' . self::PREFIX . 'core_config_data` (config_id INT AUTO_INCREMENT PRIMARY KEY, scope VARCHAR(8), scope_id INT, path VARCHAR(255), value TEXT) ENGINE=InnoDB');
        $this->db->query('CREATE TABLE `' . self::PREFIX . Repository::RECEIPT . '` (source_id INT PRIMARY KEY, target_id INT UNIQUE, state VARCHAR(24), target_hash VARCHAR(64), payload MEDIUMTEXT, backup_reference VARCHAR(255), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
    }

    private function fixtures(): void
    {
        $this->db->insert($this->repository->table('rw_shoppingfeeds_feed'), ['id' => 7, 'store_id' => 1, 'name' => 'Existing feed', 'type' => 'google_shopping', 'status' => 1, 'use_microdata' => 1, 'messages' => '[]']);
        $this->db->insert($this->repository->table('rw_shoppingfeeds_feed_config'), ['feed_id' => 7, 'path' => 'general_currency', 'value' => 'GBP']);
        $this->db->insert($this->repository->table('rw_shoppingfeeds_feed_schedule'), ['feed_id' => 7, 'start_at' => 5, 'batch_limit' => 100]);
        $this->db->insert($this->repository->table('rw_shoppingfeeds_feed_upload'), ['feed_id' => 7, 'username' => 'fixture', 'password' => $this->encryptor->encrypt('synthetic-upload-password'), 'host' => 'upload.invalid', 'port' => 22, 'path' => '/test', 'mode' => 'sftp', 'gzip' => 0]);
        $this->db->insert($this->repository->table('core_config_data'), ['scope' => 'stores', 'scope_id' => 1, 'path' => 'shoppingfeeds/log/level', 'value' => '250']);
    }

    private function import(): array
    {
        return $this->migration->import(7, $this->migration->preview(7)['token'], 'synthetic-fixture-backup');
    }

    public function testPreviewIsReadOnlyAndImportPreservesSourceAndQuarantinesSideEffects(): void
    {
        $source = $this->repository->source(7);
        $preview = $this->migration->preview(7);
        self::assertSame([], $this->repository->receipts());
        self::assertSame(64, strlen($preview['token']));
        self::assertStringNotContainsString('synthetic-upload-password', json_encode($preview));
        $receipt = $this->import();
        self::assertSame($source, $this->repository->source(7));
        $target = $this->repository->target((int)$receipt['target_id']);
        self::assertSame(0, (int)$target['feed']['status']);
        self::assertSame(0, (int)$target['feed']['use_microdata']);
        self::assertSame([], $target['schedules']);
        self::assertSame([], $target['uploads']);
        $saved = $this->repository->receipt(7);
        self::assertStringNotContainsString('upload.invalid', $saved['payload']);
        $payload = json_decode($this->encryptor->decrypt($saved['payload']), true);
        self::assertSame($source, $payload['source']);
    }
    public function testStalePreviewWritesNothing(): void
    {
        $token = $this->migration->preview(7)['token'];
        $this->db->update($this->repository->table('rw_shoppingfeeds_feed_config'), ['value' => 'EUR'], ['feed_id = ?' => 7]);
        try {
            $this->migration->import(7, $token, 'backup');
            self::fail('Stale import succeeded');
        } catch (\DomainException $e) {
            self::assertStringContainsString('stale', $e->getMessage());
        }
        self::assertSame([], $this->repository->receipts());
        self::assertSame('0', (string)$this->db->fetchOne('SELECT COUNT(*) FROM ' . $this->repository->table('mageos_shopping_feed_feed')));
    }
    public function testUnsupportedReplacementDirectiveBlocksPreviewAndApplyWithoutWrites(): void
    {
        $token = $this->migration->preview(7)['token'];
        $this->db->insert($this->repository->table('rw_shoppingfeeds_feed_config'), [
            'feed_id' => 7,
            'path' => 'filters_map_replace_empty_columns',
            'value' => '[{"column":"id","attribute":"directive_custom"}]',
        ]);
        $source = $this->repository->source(7);
        foreach (['preview', 'import'] as $action) {
            try {
                if ($action === 'preview') {
                    $this->migration->preview(7);
                } else {
                    $this->migration->import(7, $token, 'backup');
                }
                self::fail('Unsupported replacement directive passed ' . $action);
            } catch (\DomainException $e) {
                self::assertStringContainsString('directive unavailable', $e->getMessage());
                self::assertStringContainsString('filters_map_replace_empty_columns', $e->getMessage());
            }
            self::assertSame($source, $this->repository->source(7));
            self::assertSame([], $this->repository->receipts());
            self::assertSame('0', (string)$this->db->fetchOne('SELECT COUNT(*) FROM ' . $this->repository->table('mageos_shopping_feed_feed')));
        }
    }
    public function testDuplicateImportIsBlocked(): void
    {
        $this->import();
        $this->expectExceptionMessage('already has a migration receipt');
        $this->import();
    }
    public function testRollbackDeletesOnlyUnchangedImportAndRetainsReceipt(): void
    {
        $this->import();
        $review = $this->migration->review(7, 'rollback');
        $result = $this->migration->transition(7, 'rollback', $review['token']);
        self::assertSame('rolled_back', $result['state']);
        self::assertCount(1, $this->repository->candidates());
        self::assertSame('0', (string)$this->db->fetchOne('SELECT COUNT(*) FROM ' . $this->repository->table('mageos_shopping_feed_feed_config')));
    }
    public function testRollbackRefusesToDiscardEdits(): void
    {
        $receipt = $this->import();
        $this->db->update($this->repository->table('mageos_shopping_feed_feed'), ['name' => 'User edit'], ['id = ?' => $receipt['target_id']]);
        $this->expectExceptionMessage('discard changes');
        $this->migration->review(7, 'rollback');
    }
    public function testActivationRequiresOriginalFeedDisabled(): void
    {
        $this->import();
        $this->expectExceptionMessage('Disable the original feed');
        $this->migration->review(7, 'activate');
    }
    public function testActivationRestoresSchedulesAndReadableCredentials(): void
    {
        $receipt = $this->import();
        $this->db->update($this->repository->table('rw_shoppingfeeds_feed'), ['status' => 0], ['id = ?' => 7]);
        $review = $this->migration->review(7, 'activate');
        $this->migration->transition(7, 'activate', $review['token']);
        $target = $this->repository->target((int)$receipt['target_id']);
        self::assertCount(1, $target['schedules']);
        self::assertSame('synthetic-upload-password', $this->encryptor->decrypt($target['uploads'][0]['password']));
        self::assertSame(1, (int)$target['feed']['status']);
        self::assertSame(0, (int)$this->repository->source(7)['feed']['status']);
    }
    public function testDatabaseFailureRollsBackFeedAndConfig(): void
    {
        $token = $this->migration->preview(7)['token'];
        $this->db->query('ALTER TABLE `' . $this->repository->table(Repository::RECEIPT) . '` ADD CONSTRAINT reject_fixture CHECK (source_id <> 7)');
        try {
            $this->migration->import(7, $token, 'backup');
            self::fail('Expected insert failure');
        } catch (\Exception $e) {
            self::assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class, $e);
        }
        self::assertSame('0', (string)$this->db->fetchOne('SELECT COUNT(*) FROM ' . $this->repository->table('mageos_shopping_feed_feed')));
    }
    public function testCompletedProcessHistoryDoesNotBlockActivation(): void
    {
        $receipt = $this->import();
        $this->db->update($this->repository->table('rw_shoppingfeeds_feed'), ['status' => 0], ['id = ?' => 7]);
        foreach (['rw_shoppingfeeds_' => 7, 'mageos_shopping_feed_' => (int)$receipt['target_id']] as $prefix => $id) {
            $this->db->insert($this->repository->table($prefix . 'process'), ['feed_id' => $id, 'status' => 1]);
        }
        self::assertSame('activate', $this->migration->review(7, 'activate')['action']);
    }
    public function testPendingProcessBlocksActivation(): void
    {
        $this->import();
        $this->db->update($this->repository->table('rw_shoppingfeeds_feed'), ['status' => 0], ['id = ?' => 7]);
        $this->db->insert($this->repository->table('rw_shoppingfeeds_process'), ['feed_id' => 7, 'status' => 0]);
        $this->expectExceptionMessage('queued or processing');
        $this->migration->review(7, 'activate');
    }
    public function testEditedSourcePreventsActivation(): void
    {
        $this->import();
        $this->db->update($this->repository->table('rw_shoppingfeeds_feed'), ['status' => 0], ['id = ?' => 7]);
        $this->db->update($this->repository->table('rw_shoppingfeeds_feed_config'), ['value' => 'EUR'], ['feed_id = ?' => 7]);
        $this->expectExceptionMessage('Legacy configuration changed');
        $this->migration->review(7, 'activate');
    }
    public function testChangedTargetInvalidatesReviewedActivation(): void
    {
        $receipt = $this->import();
        $this->db->update($this->repository->table('rw_shoppingfeeds_feed'), ['status' => 0], ['id = ?' => 7]);
        $token = $this->migration->review(7, 'activate')['token'];
        $this->db->update($this->repository->table('mageos_shopping_feed_feed'), ['name' => 'User edit'], ['id = ?' => $receipt['target_id']]);
        $this->expectExceptionMessage('stale');
        $this->migration->transition(7, 'activate', $token);
    }
    public function testDatabaseFailureDuringActivationRestoresHeldState(): void
    {
        $receipt = $this->import();
        $this->db->update($this->repository->table('rw_shoppingfeeds_feed'), ['status' => 0], ['id = ?' => 7]);
        $token = $this->migration->review(7, 'activate')['token'];
        $this->db->query('ALTER TABLE `' . $this->repository->table('mageos_shopping_feed_feed_upload') . '` ADD CONSTRAINT reject_upload CHECK (port <> 22)');
        try {
            $this->migration->transition(7, 'activate', $token);
            self::fail('Expected write failure');
        } catch (\Exception $e) {
            self::assertNotInstanceOf(\PHPUnit\Framework\AssertionFailedError::class, $e);
        }
        self::assertSame([], $this->repository->target((int)$receipt['target_id'])['schedules']);
        self::assertSame('imported', $this->repository->receipt(7)['state']);
    }
}
