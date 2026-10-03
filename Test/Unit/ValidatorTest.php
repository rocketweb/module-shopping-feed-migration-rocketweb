<?php

declare(strict_types=1);

namespace RocketWeb\ShoppingFeedMigration\Test\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ValidatorTest extends TestCase
{
    public function testValidatorInspectsFilesWhenThePackageIsUnderVendor(): void
    {
        $root = dirname(__DIR__, 2);
        $temporary = sys_get_temp_dir() . '/migration-validator-' . bin2hex(random_bytes(8));
        $package = $temporary . '/vendor/rocketweb/migration';
        $files = ['composer.json', 'registration.php', 'dev/validate.php', 'etc/module.xml'];
        try {
            foreach ($files as $file) {
                if (!is_dir(dirname($package . '/' . $file))) {
                    mkdir(dirname($package . '/' . $file), 0700, true);
                }
                copy($root . '/' . $file, $package . '/' . $file);
            }
            $process = new Process([PHP_BINARY, $package . '/dev/validate.php', (string)getenv('MAGENTO_ROOT')]);
            $process->run();
            self::assertSame(0, $process->getExitCode(), $process->getErrorOutput() . $process->getOutput());
            self::assertStringContainsString('Validated 2 PHP/template files, 1 XML documents', $process->getOutput());
        } finally {
            foreach ($files as $file) {
                if (is_file($package . '/' . $file)) {
                    unlink($package . '/' . $file);
                }
            }
            foreach ([$package . '/dev', $package . '/etc', $package, dirname($package), dirname($package, 2), $temporary] as $directory) {
                if (is_dir($directory)) {
                    rmdir($directory);
                }
            }
        }
    }

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
