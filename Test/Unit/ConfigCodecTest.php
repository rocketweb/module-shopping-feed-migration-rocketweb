<?php

declare(strict_types=1);

namespace RocketWeb\ShoppingFeedMigration\Test\Unit;

use PHPUnit\Framework\TestCase;
use RocketWeb\ShoppingFeedMigration\Model\ConfigCodec;

class ConfigCodecTest extends TestCase
{
    public function testPreservesStructuredSettingsAndNumericLookingStrings(): void
    {
        $codec = new ConfigCodec();
        self::assertSame('001', $codec->decode('001'));
        self::assertSame(['42' => 'Apparel > Shoes'], $codec->decode('a:1:{i:42;s:15:"Apparel > Shoes";}'));
        self::assertSame([['column' => 'id', 'attribute' => 'sku']], $codec->decode('[{"column":"id","attribute":"sku"}]'));
        self::assertSame('__mageos_shopping_feed_string__:"[literal]"', $codec->encode('[literal]'));
    }
    /** @dataProvider invalidSettings */
    public function testRejectsUnsafeSerialization(string $input): void
    {
        $this->expectException(\DomainException::class);
        (new ConfigCodec())->decode($input);
    }
    public static function invalidSettings(): array
    {
        return [['O:8:"stdClass":0:{}'], ['a:1:{i:0;R:1;}'], ['a:2:{bad'], ['{"incomplete":']];
    }
}
