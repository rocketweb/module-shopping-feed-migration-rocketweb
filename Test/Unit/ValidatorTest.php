<?php

declare(strict_types=1);

namespace RocketWeb\ShoppingFeedMigration\Test\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ValidatorTest extends TestCase
{
    public function testValidatorAcceptsAnAlreadyInstalledMigrationModule(): void
    {
        $root = dirname(__DIR__, 2);
        $magento = (string)getenv('MAGENTO_ROOT');
        $code = 'require ' . var_export($magento . '/vendor/autoload.php', true) . ';'
            . '$registrar = new \\Magento\\Framework\\Component\\ComponentRegistrar();'
            . 'if (!$registrar->getPath($registrar::MODULE, "RocketWeb_ShoppingFeedMigration")) {'
            . '$registrar::register($registrar::MODULE, "RocketWeb_ShoppingFeedMigration", sys_get_temp_dir() . "/installed-migration");'
            . '}'
            . '$argv = ["validate.php", ' . var_export($magento, true) . '];'
            . 'require ' . var_export($root . '/dev/validate.php', true) . ';';
        $process = new Process([PHP_BINARY, '-r', $code]);
        $process->run();
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput() . $process->getOutput());
        self::assertStringContainsString('XML documents, and package identity.', $process->getOutput());
    }
}
