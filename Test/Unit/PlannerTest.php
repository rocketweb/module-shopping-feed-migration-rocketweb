<?php

declare(strict_types=1);

namespace RocketWeb\ShoppingFeedMigration\Test\Unit;

use PHPUnit\Framework\TestCase;
use RocketWeb\ShoppingFeedMigration\Model\{Planner, Definitions, ConfigCodec};
use Magento\Framework\Encryption\EncryptorInterface;

class PlannerTest extends TestCase
{
    public static function definition(): array
    {
        return ['default_feed_config' => ['general' => ['feed_dir' => 'pub/media/feeds', 'currency' => 'USD'],
            'file' => ['feed' => 'shopping_feed_%s.txt', 'log' => 'feed_%s.log'],
            'columns' => ['product_columns' => [['column' => 'id', 'attribute' => 'sku']]],
            'categories' => ['provider_taxonomy_by_category' => []]], 'directives' => []];
    }
    public static function source(): array
    {
        return ['feed' => ['id' => '7', 'name' => 'Google', 'type' => 'google_shopping', 'store_id' => '1'],
            'config' => [['path' => 'general_currency', 'value' => 'GBP']], 'schedules' => [],
            'uploads' => [['password' => 'cipher']], 'system' => []];
    }
    private function planner(bool $canDecrypt = true): Planner
    {
        $definitions = $this->createMock(Definitions::class);
        $definitions->method('get')->willReturn([self::definition(), self::definition()]);
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturn($canDecrypt ? 'test-only-password' : '');
        return new Planner($definitions, new ConfigCodec(), $encryptor);
    }
    public function testCapturesDefaultsAndPreservesFilenameIdWithoutExposingSecrets(): void
    {
        $planner = $this->planner();
        $plan = $planner->build(self::source());
        self::assertSame('GBP', $plan['config']['general_currency']);
        self::assertSame('shopping_feed_7.txt', $plan['config']['file_feed']);
        self::assertSame('pub/media/mageos-shopping-feed/rocketweb-7', $plan['config']['general_feed_dir']);
        self::assertSame('[{"column":"id","attribute":"sku"}]', $plan['config']['columns_product_columns']);
        $report = json_encode($planner->report($plan));
        self::assertStringNotContainsString('cipher', $report);
        self::assertStringNotContainsString('test-only-password', $report);
    }
    public function testUnknownSettingsBlockImport(): void
    {
        $source = self::source();
        $source['config'][] = ['path' => 'custom_unsupported', 'value' => 'anything'];
        $this->expectExceptionMessage('Unsupported configuration path');
        $this->planner()->build($source);
    }
    public function testUnreadableCredentialsBlockImport(): void
    {
        $this->expectExceptionMessage('cannot be decrypted');
        $this->planner(false)->build(self::source());
    }
    public function testUnknownDirectivesBlockImport(): void
    {
        $source = self::source();
        $source['config'][] = ['path' => 'columns_product_columns', 'value' => '[{"attribute":"directive_custom"}]'];
        $this->expectExceptionMessage('directive unavailable');
        $this->planner()->build($source);
    }
    public function testPathTraversalBlocksImport(): void
    {
        $source = self::source();
        $source['config'][] = ['path' => 'file_feed', 'value' => '../secret.txt'];
        $this->expectExceptionMessage('filename template');
        $this->planner()->build($source);
    }
    /** @dataProvider invalidFilenames */
    public function testRejectsFilenamesTheDestinationCannotGenerate(string $path, string $value): void
    {
        $source = self::source();
        $source['config'][] = ['path' => $path, 'value' => $value];
        $this->expectExceptionMessage('filename template');
        $this->planner()->build($source);
    }

    public static function invalidFilenames(): array
    {
        return [['file_feed', 'feed.log'], ['file_log', 'log.txt'], ['file_feed', '.hidden.txt'], ['file_feed', '']];
    }
}
