<?php

declare(strict_types=1);

namespace RocketWeb\ShoppingFeedMigration\Test\Unit;

use Magento\Framework\Config\Dom;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class AclTest extends TestCase
{
    public function testMergedAclDoesNotDuplicateDestinationResources(): void
    {
        $root = getenv('SHOPPING_FEED_ROOT');
        if (!$root) {
            self::markTestSkipped('Set SHOPPING_FEED_ROOT to test the real destination ACL.');
        }
        $validation = $this->createMock(\Magento\Framework\Config\ValidationStateInterface::class);
        $validation->method('isValidationRequired')->willReturn(false);
        $merged = new Dom(
            file_get_contents($root . '/etc/acl.xml'),
            $validation,
            ['/config/acl/resources(/resource)+' => 'id']
        );
        $merged->merge(file_get_contents(dirname(__DIR__, 2) . '/etc/acl.xml'));
        $ids = [];
        foreach ($merged->getDom()->getElementsByTagName('resource') as $resource) {
            $id = $resource->getAttribute('id');
            self::assertArrayNotHasKey($id, $ids, 'Duplicate ACL resource blocks Admin login: ' . $id);
            $ids[$id] = true;
        }
        self::assertArrayHasKey('RocketWeb_ShoppingFeedMigration::migration', $ids);
    }
}
