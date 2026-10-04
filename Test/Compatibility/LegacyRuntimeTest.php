<?php

declare(strict_types=1);

namespace RocketWeb\ShoppingFeedMigration\Test\Compatibility;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Gallery\ReadHandler;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DataObject;
use Magento\Store\Model\Store;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use RocketWeb\ShoppingFeeds\Model\Feed;
use RocketWeb\ShoppingFeeds\Model\Logger;
use RocketWeb\ShoppingFeeds\Model\Generator\Cache;
use RocketWeb\ShoppingFeeds\Model\Generator\Cache\ShippingProvider;
use RocketWeb\ShoppingFeeds\Model\Product\Adapter\AdapterAbstract;
use RocketWeb\ShoppingFeeds\Model\Product\Filter;
use RocketWeb\ShoppingFeeds\Model\Product\Mapper\Generic\Simple\AdditionalImageLink;
use RocketWeb\ShoppingFeeds\Model\Product\Mapper\Generic\Simple\Shipping;
use RocketWeb\ShoppingFeeds\Model\Product\Mapper\Generic\Simple\Url;
use RocketWeb\ShoppingFeeds\Model\Product\Mapper\Generic\Configurable\Associated\Url as ConfigurableUrl;
use RocketWeb\ShoppingFeeds\Model\Product\Mapper\Generic\Grouped\Associated\Url as GroupedUrl;
use RocketWeb\ShoppingFeedsGoogle\Model\Product\Mapper\Google\Simple\IdentifierExists;
use Symfony\Component\Process\Process;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class LegacyRuntimeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        set_error_handler(static function (int $severity, string $message): never {
            throw new \ErrorException($message, 0, $severity);
        }, E_DEPRECATED | E_USER_DEPRECATED);
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        parent::tearDown();
    }

    public function testSavingColumnsPreservesNullDefaultsAndCleansStrings(): void
    {
        $columns = [['column' => "shipping\tweight", 'attribute' => 'directive_shipping_weight',
            'param' => null, 'order' => 0]];
        $config = new DataObject(['columns_product_columns' => $columns]);
        $feed = $this->getMockBuilder(Feed::class)->disableOriginalConstructor()
            ->onlyMethods(['getConfig', 'getId'])->getMock();
        $feed->method('getId')->willReturn(1);
        $feed->method('getConfig')->willReturnCallback(static fn($path = null) =>
            $path === null ? $config : $config->getData($path));
        $events = new \ReflectionProperty(Feed::class, '_eventManager');
        $events->setValue($feed, $this->createMock(\Magento\Framework\Event\ManagerInterface::class));
        set_error_handler(static function (int $severity, string $message): never {
            throw new \ErrorException($message, 0, $severity);
        });
        try {
            $feed->beforeSave();
        } finally {
            restore_error_handler();
        }
        $saved = $config->getData('columns_product_columns')[0];
        self::assertSame('shipping weight', $saved['column']);
        self::assertNull($saved['param']);
        self::assertSame(0, $saved['order']);
    }

    public function testGenerateCommandLoadsWithInstalledSymfony(): void
    {
        $this->assertCommandLoads('GenerateCommand');
    }

    public function testScheduleCommandLoadsWithInstalledSymfony(): void
    {
        $this->assertCommandLoads('ScheduleCommand');
    }

    private function assertCommandLoads(string $name): void
    {
        $class = 'RocketWeb\\ShoppingFeeds\\Console\\Command\\' . $name;
        $process = new Process([PHP_BINARY, '-r',
            'require ' . var_export(__DIR__ . '/bootstrap.php', true) . '; '
            . 'exit(class_exists(' . var_export($class, true) . ') ? 0 : 1);'
        ]);
        $process->run();
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput() . $process->getOutput());
    }

    /** @dataProvider imageDelimiters */
    #[DataProvider('imageDelimiters')]
    public function testAdditionalImagesUseFeedDelimiter(string $delimiter, string $other, string $separator): void
    {
        $result = $this->mapImages($delimiter, $other, [
            ['file' => '/base.png', 'disabled' => false],
            ['file' => '/one.png', 'disabled' => false],
            ['file' => '/hidden.png', 'disabled' => true],
            ['file' => '/two.png', 'disabled' => false],
        ]);
        self::assertSame('https://example.test/media/one.png' . $separator . 'https://example.test/media/two.png', $result);
    }

    public static function imageDelimiters(): array
    {
        return [
            'tab feed' => ["\t", '', ','],
            'comma feed' => [',', '', '|'],
            'custom comma delimiter' => ['other', ' , ', '|'],
        ];
    }

    public function testEmptyGalleryReturnsEmptyField(): void
    {
        self::assertSame('', $this->mapImages("\t", '', []));
    }

    /** @dataProvider emptyShippingCountries */
    #[DataProvider('emptyShippingCountries')]
    public function testUnconfiguredShippingDoesNotRequestRates($countries): void
    {
        $cache = $this->createMock(Cache::class);
        $cache->expects(self::never())->method('getCache');
        self::assertSame('', $this->shippingMapper($countries, $cache)->map());
    }

    public static function emptyShippingCountries(): array
    {
        return [[null], [''], [false], ['US'], [[]], [['', null, false]]];
    }

    public function testConfiguredShippingStillUsesItsCachedRate(): void
    {
        $cache = $this->createMock(Cache::class);
        $cache->expects(self::once())->method('getCache')
            ->with(['shipping', 'map', 'product', 'rate', 7], false)->willReturn('US:::4.95 USD');
        self::assertSame('US:::4.95 USD', $this->shippingMapper(['US', ''], $cache)->map());
    }

    /** @dataProvider urlQueries */
    #[DataProvider('urlQueries')]
    public function testUrlQueryHandlesNullAndPreservesConfiguredValues(string $class, ?string $query, string $suffix): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn(7);
        $product->method('getProductUrl')->willReturn('https://example.test/product.html');
        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://example.test/');
        $product->method('getStore')->willReturn($store);
        $filter = $this->createMock(Filter::class);
        $filter->expects(self::once())->method('findAndReplace')->with(self::anything(), 'link');
        $adapter = new class (['product' => $product, 'filter' => $filter, 'feed' => new DataObject(['store_id' => 1])]) extends DataObject {
            public function getUrlOptions($product): array
            {
                return [137 => 5];
            }
        };
        $adapter->setData('parent_adapter', $adapter);
        $logger = $this->createMock(Logger::class);
        $mapper = $class === ConfigurableUrl::class
            ? new $class($logger, $this->createMock(ScopeConfigInterface::class)) : new $class($logger);
        $mapper->addAdapter($adapter);
        self::assertSame('https://example.test/product.html' . $suffix, $mapper->map(['column' => 'link', 'param' => $query]));
    }

    public static function urlQueries(): array
    {
        return [
            [Url::class, null, ''],
            [Url::class, '?utm=test', '?utm=test'],
            [ConfigurableUrl::class, null, '#137=5'],
            [ConfigurableUrl::class, '?utm=test', '?utm=test#137=5'],
            [GroupedUrl::class, null, '?prod_id=7'],
            [GroupedUrl::class, '?utm=test', '?utm=test&prod_id=7'],
        ];
    }

    /** @dataProvider identifierColumns */
    #[DataProvider('identifierColumns')]
    public function testGoogleIdentifiersHandleNullAndConfiguredColumns(?string $param, array $values, string $expected): void
    {
        if (!getenv('LEGACY_GOOGLE_PATCH_ROOT')) {
            self::markTestSkipped('Set LEGACY_GOOGLE_PATCH_ROOT to exercise the Google add-on patch.');
        }
        $feed = $this->createMock(Feed::class);
        $feed->method('getColumnsMap')->willReturn(array_map(static fn(string $key): array => ['column' => $key], array_keys($values)));
        $filter = $this->createMock(Filter::class);
        $filter->expects(self::once())->method('findAndReplace')->with(self::anything(), 'identifier_exists');
        $adapter = $this->createMock(AdapterAbstract::class);
        $adapter->method('getFeed')->willReturn($feed);
        $adapter->method('getFilter')->willReturn($filter);
        $adapter->method('getMapValue')->willReturnCallback(static fn(array $map): string => $values[$map['column']]);
        $mapper = new IdentifierExists($this->createMock(Logger::class));
        $mapper->addAdapter($adapter);
        self::assertSame($expected, $mapper->map(['column' => 'identifier_exists', 'param' => $param]));
    }

    public static function identifierColumns(): array
    {
        return [
            [null, [], 'FALSE'],
            [null, ['brand' => 'Synthetic brand', 'gtin' => '1234567890123'], ''],
            ['brand,mpn', ['brand' => 'Synthetic brand', 'mpn' => 'synthetic-1'], ''],
            ['brand,mpn', ['mpn' => 'synthetic-1'], 'FALSE'],
        ];
    }

    private function shippingMapper($countries, Cache $cache): Shipping
    {
        $feed = $this->createMock(Feed::class);
        $feed->method('getConfig')->with('shipping_country')->willReturn($countries);
        $product = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->getMock();
        $product->method('getId')->willReturn(7);
        $adapter = $this->createMock(AdapterAbstract::class);
        $adapter->method('getFeed')->willReturn($feed);
        $adapter->method('getProduct')->willReturn($product);
        $provider = $this->createMock(ShippingProvider::class);
        $provider->expects(self::never())->method('prepareCache');
        $mapper = new Shipping($cache, $provider, $this->createMock(Logger::class), $this->createMock(ScopeConfigInterface::class));
        $mapper->addAdapter($adapter);
        return $mapper;
    }

    private function mapImages(string $delimiter, string $other, array $images): string
    {
        $product = $this->getMockBuilder(Product::class)->disableOriginalConstructor()
            ->onlyMethods(['getMediaGalleryImages'])->getMock();
        $product->setData('image', '/base.png');
        $product->method('getMediaGalleryImages')->willReturn($images);
        $gallery = $this->createMock(ReadHandler::class);
        $gallery->expects(self::once())->method('execute')->with($product);
        $feed = $this->createMock(Feed::class);
        $feed->method('getConfig')->willReturnCallback(static fn(string $path): string =>
            $path === 'output_params_delimiter' ? $delimiter : $other);
        $filter = $this->createMock(Filter::class);
        $filter->expects(self::once())->method('findAndReplace')->with(self::anything(), 'additional_image_link');
        $adapter = $this->createMock(AdapterAbstract::class);
        $adapter->method('getProduct')->willReturn($product);
        $adapter->method('getFeed')->willReturn($feed);
        $adapter->method('getFilter')->willReturn($filter);
        $adapter->method('getData')->with('images_url_prefix')->willReturn('https://example.test/media');
        $mapper = new AdditionalImageLink($this->createMock(Logger::class), $gallery);
        $mapper->addAdapter($adapter);
        return $mapper->map(['column' => 'additional_image_link']);
    }
}
