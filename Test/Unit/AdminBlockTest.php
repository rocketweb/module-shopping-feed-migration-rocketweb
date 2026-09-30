<?php

declare(strict_types=1);

namespace RocketWeb\ShoppingFeedMigration\Test\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class AdminBlockTest extends TestCase
{
    public function testAdminBlockLoadsWithTheInstalledMagentoParent(): void
    {
        // Isolate inheritance fatals so a broken Admin block does not kill the suite.
        $process = new Process([PHP_BINARY, '-r',
            'require ' . var_export(dirname(__DIR__) . '/bootstrap.php', true) . '; '
            . 'exit(class_exists(\\RocketWeb\\ShoppingFeedMigration\\Block\\Adminhtml\\Migration::class) ? 0 : 1);'
        ]);
        $process->run();
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput() . $process->getOutput());
    }
}
