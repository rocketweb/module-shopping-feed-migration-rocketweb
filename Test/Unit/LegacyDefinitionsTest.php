<?php

declare(strict_types=1);

namespace RocketWeb\ShoppingFeedMigration\Test\Unit;

use PHPUnit\Framework\TestCase;
use Magento\Framework\Config\{FileResolverInterface, ValidationStateInterface};
use Magento\Framework\Json\Decoder;
use Magento\Framework\Encryption\EncryptorInterface;
use MageOS\ShoppingFeed\Model\FeedTypes\Config\{Reader, Converter, SchemaLocator};
use RocketWeb\ShoppingFeedMigration\Model\{Definitions, Planner, ConfigCodec};

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class LegacyDefinitionsTest extends TestCase
{
    public function testRealLegacyDefinitionsMergeAndPlanAllThreeTypes(): void
    {
        $legacyRoot = getenv('LEGACY_FEED_ROOT');
        $targetRoot = getenv('SHOPPING_FEED_ROOT');
        if (!$legacyRoot || !$targetRoot) {
            self::markTestSkipped('Set LEGACY_FEED_ROOT and SHOPPING_FEED_ROOT for original XML compatibility checks.');
        }
        $legacyFiles = [];
        foreach (['m2-shopping-feed', 'm2-shopping-feed-google', 'm2-shopping-feed-google-inventory', 'm2-shopping-feed-google-promotions'] as $module) {
            $path = $legacyRoot . '/' . $module . '/etc/shoppingfeeds.xml';
            $legacyFiles[$path] = file_get_contents($path);
        }
        $legacy = $this->read($legacyFiles, (new \RocketWeb\ShoppingFeedMigration\Model\LegacySchemaLocator())->getSchema());
        $target = $this->read(['core' => file_get_contents($targetRoot . '/etc/mageos_shopping_feed.xml')], $targetRoot . '/etc/mageos_shopping_feed.xsd');
        foreach (['generic', 'google_shopping', 'google_local_inventory'] as $type) {
            $definitions = $this->createMock(Definitions::class);
            $definitions->method('get')->willReturn([$legacy['feed'][$type], $target['feed'][$type]]);
            $encryptor = $this->createMock(EncryptorInterface::class);
            $planner = new Planner($definitions, new ConfigCodec(), $encryptor);
            $source = PlannerTest::source();
            $source['feed']['type'] = $type;
            $source['uploads'] = [];
            $plan = $planner->build($source);
            self::assertGreaterThan(40, count($plan['config']));
            self::assertSame('GBP', $plan['config']['general_currency']);
            self::assertSame('pub/media/mageos-shopping-feed/rocketweb-7', $plan['config']['general_feed_dir']);
        }
        self::assertSame('promo_%s.txt', $legacy['feed']['google_shopping']['default_feed_config']['file']['promotion']);
    }

    private function read(array $files, string $schemaFile): array
    {
        $resolver = $this->createMock(FileResolverInterface::class);
        $resolver->method('get')->willReturn($files);
        $schema = $this->createMock(SchemaLocator::class);
        $schema->method('getSchema')->willReturn($schemaFile);
        $validation = $this->createMock(ValidationStateInterface::class);
        $validation->method('isValidationRequired')->willReturn(true);
        return (new Reader($resolver, new Converter(new Decoder(new \Magento\Framework\Serialize\Serializer\Json())), $schema, $validation, 'shoppingfeeds.xml'))->read();
    }
}
