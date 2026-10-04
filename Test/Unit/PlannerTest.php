<?php

declare(strict_types=1);

namespace RocketWeb\ShoppingFeedMigration\Test\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RocketWeb\ShoppingFeedMigration\Model\{Planner, Definitions, ConfigCodec};
use Magento\Framework\Encryption\EncryptorInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
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
    private function planner(bool $canDecrypt = true, array $directives = []): Planner
    {
        $definitions = $this->createMock(Definitions::class);
        $target = self::definition();
        $target['directives'] = $directives;
        $definitions->method('get')->willReturn([self::definition(), $target]);
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
    /** @dataProvider columnMapPaths */
    #[DataProvider('columnMapPaths')]
    public function testUnknownDirectivesBlockImport(string $path): void
    {
        $source = self::source();
        $source['config'][] = ['path' => $path, 'value' => '[{"column":"id","attribute":"directive_custom"}]'];
        $this->expectExceptionMessage('directive unavailable');
        $this->planner()->build($source);
    }

    /** @dataProvider invalidColumnMaps */
    #[DataProvider('invalidColumnMaps')]
    public function testMalformedColumnMapsBlockImport(string $path, mixed $map): void
    {
        $source = self::source();
        $source['config'][] = ['path' => $path, 'value' => serialize($map)];
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage($path);
        $this->planner()->build($source);
    }

    public static function invalidColumnMaps(): array
    {
        $cases = [];
        foreach (self::columnMapPaths() as [$path]) {
            foreach (
                [
                    'scalar map' => 'invalid',
                    'scalar row' => ['invalid'],
                    'missing column' => [['attribute' => 'sku']],
                    'empty column' => [['column' => '', 'attribute' => 'sku']],
                    'missing attribute' => [['column' => 'id']],
                    'array attribute' => [['column' => 'id', 'attribute' => ['sku']]],
                ] as $name => $map
            ) {
                $cases[$path . ': ' . $name] = [$path, $map];
            }
        }
        return $cases;
    }

    /** @dataProvider validReplacementMaps */
    #[DataProvider('validReplacementMaps')]
    public function testSupportedReplacementRulesArePreserved(mixed $map): void
    {
        $source = self::source();
        $source['config'][] = ['path' => 'filters_map_replace_empty_columns', 'value' => serialize($map)];
        $plan = $this->planner()->build($source);
        self::assertSame((new ConfigCodec())->encode($map), $plan['config']['filters_map_replace_empty_columns']);
        self::assertSame($source, $plan['source']);
    }

    public static function validReplacementMaps(): array
    {
        return [
            'empty XML default' => [null],
            'empty saved value' => [''],
            'empty list' => [[]],
            'attribute fallback' => [[['column' => 'title', 'attribute' => 'name', 'order' => 2]]],
            'static fallback without attribute' => [[['column' => 'brand', 'static' => 'Fixture brand']]],
            'static fallback with empty attribute' => [[['column' => 'brand', 'attribute' => '', 'static' => 'Fixture brand']]],
            'static fallback with directive' => [[['column' => 'brand', 'attribute' => 'directive_static_value', 'static' => 'Fixture brand']]],
        ];
    }

    /** @dataProvider columnMapPaths */
    #[DataProvider('columnMapPaths')]
    public function testDestinationRegisteredDirectivesArePreserved(string $path): void
    {
        $map = [['column' => 'id', 'attribute' => 'directive_custom', 'param' => 'fixture']];
        $source = self::source();
        $source['config'][] = ['path' => $path, 'value' => json_encode($map)];
        $plan = $this->planner(true, ['directive_custom' => []])->build($source);
        self::assertSame($map, json_decode($plan['config'][$path], true));
    }
    public function testPathTraversalBlocksImport(): void
    {
        $source = self::source();
        $source['config'][] = ['path' => 'file_feed', 'value' => '../secret.txt'];
        $this->expectExceptionMessage('filename template');
        $this->planner()->build($source);
    }

    /** @dataProvider columnMapPaths */
    #[DataProvider('columnMapPaths')]
    public function testNullColumnParametersNormalizeWithoutChangingOtherValuesOrSource(string $path): void
    {
        $columns = [
            ['column' => 'null', 'attribute' => 'directive_url', 'param' => null],
            ['column' => 'attribute-default', 'attribute' => 'sku', 'param' => null],
            ['column' => 'missing', 'attribute' => 'sku'],
            ['column' => 'empty', 'attribute' => 'sku', 'param' => ''],
            ['column' => 'zero', 'attribute' => 'sku', 'param' => 0],
            ['column' => 'string-zero', 'attribute' => 'sku', 'param' => '0'],
            ['column' => 'false', 'attribute' => 'sku', 'param' => false],
            ['column' => 'value', 'attribute' => 'sku', 'param' => '?utm=test'],
        ];
        $source = self::source();
        $source['config'][] = ['path' => $path, 'value' => json_encode($columns)];
        $planner = $this->planner(true, ['directive_url' => []]);
        $plan = $planner->build($source);
        $columns[0]['param'] = '';
        self::assertSame($columns, json_decode($plan['config'][$path], true));
        self::assertSame($source, $plan['source']);
        self::assertContains($path, array_column($planner->report($plan)['changes'], 'setting'));
    }

    public static function columnMapPaths(): array
    {
        return [['columns_product_columns'], ['filters_map_replace_empty_columns']];
    }

    /** @dataProvider columnMapPaths */
    #[DataProvider('columnMapPaths')]
    public function testNullWeightParameterRetainsTheMapperDefault(string $path): void
    {
        $columns = [['column' => 'shipping_weight', 'attribute' => 'directive_shipping_weight', 'param' => null]];
        $source = self::source();
        $source['config'][] = ['path' => $path, 'value' => json_encode($columns)];
        $plan = $this->planner(true, ['directive_shipping_weight' => []])->build($source);
        self::assertSame($columns, json_decode($plan['config'][$path], true));
    }

    public function testInheritedNullColumnParameterIsNormalized(): void
    {
        $legacy = self::definition();
        $legacy['default_feed_config']['columns']['product_columns'][0]['param'] = null;
        $legacy['default_feed_config']['columns']['product_columns'][0]['attribute'] = 'directive_url';
        $target = self::definition();
        $target['directives']['directive_url'] = [];
        $definitions = $this->createMock(Definitions::class);
        $definitions->method('get')->willReturn([$legacy, $target]);
        $source = self::source();
        $source['uploads'] = [];
        $planner = new Planner($definitions, new ConfigCodec(), $this->createMock(EncryptorInterface::class));
        $columns = json_decode($planner->build($source)['config']['columns_product_columns'], true);
        self::assertSame('', $columns[0]['param']);
    }
    /** @dataProvider invalidFilenames */
    #[DataProvider('invalidFilenames')]
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
