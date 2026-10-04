<?php

declare(strict_types=1);

namespace RocketWeb\ShoppingFeedMigration\Test\Unit;

use PHPUnit\Framework\TestCase;
use RocketWeb\ShoppingFeedMigration\Console\MigrateCommand;
use RocketWeb\ShoppingFeedMigration\Model\{Migration, Repository};
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class CommandTest extends TestCase
{
    public function testImportWithoutApplyIsOnlyPreview(): void
    {
        $migration = $this->createMock(Migration::class);
        $migration->expects(self::once())->method('preview')->with(7)->willReturn(['token' => 'preview']);
        $migration->expects(self::never())->method('import');
        $tester = new CommandTester(new MigrateCommand($migration, $this->createMock(Repository::class)));
        self::assertSame(0, $tester->execute(['action' => 'import', '--feed' => '7']));
        self::assertStringContainsString('preview', $tester->getDisplay());
    }

    public function testAdapterErrorsDoNotExposeCredentials(): void
    {
        $migration = $this->createMock(Migration::class);
        $migration->method('preview')->willThrowException(new \RuntimeException('SQL with synthetic-sensitive-value'));
        $tester = new CommandTester(new MigrateCommand($migration, $this->createMock(Repository::class)));
        self::assertSame(1, $tester->execute(['action' => 'preview', '--feed' => '7']));
        self::assertStringNotContainsString('synthetic-sensitive-value', $tester->getDisplay());
    }

    public function testInvalidIdDoesNotInvokeMigration(): void
    {
        $migration = $this->createMock(Migration::class);
        $migration->expects(self::never())->method('preview');
        $tester = new CommandTester(new MigrateCommand($migration, $this->createMock(Repository::class)));
        self::assertSame(1, $tester->execute(['action' => 'preview', '--feed' => '../7']));
    }
}
