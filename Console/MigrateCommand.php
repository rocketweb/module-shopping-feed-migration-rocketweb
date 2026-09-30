<?php

declare(strict_types=1);

namespace RocketWeb\ShoppingFeedMigration\Console;

use RocketWeb\ShoppingFeedMigration\Model\Migration;
use RocketWeb\ShoppingFeedMigration\Model\Repository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateCommand extends Command
{
    public function __construct(private Migration $migration, private Repository $repository)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('shopping-feed:migrate:rocketweb')
            ->setDescription('Preview, import, activate or roll back legacy Rocket Web feed configuration.')
            ->addArgument('action', InputArgument::OPTIONAL, 'list, preview, import, activate or rollback', 'list')
            ->addOption('feed', null, InputOption::VALUE_REQUIRED, 'Legacy feed ID')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Apply the reviewed action; otherwise preview only')
            ->addOption('token', null, InputOption::VALUE_REQUIRED, 'Token from the matching current preview')
            ->addOption('backup-reference', null, InputOption::VALUE_REQUIRED, 'Reference to a completed database backup; never a credential');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $action = (string)$input->getArgument('action');
            if ($action === 'list') {
                $result = ['feeds' => $this->repository->candidates(), 'receipts' => $this->repository->receipts()];
            } else {
                $id = filter_var($input->getOption('feed'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                if (!$id) {
                    throw new \DomainException('Provide a positive --feed ID.');
                }
                if ($action === 'preview' || ($action === 'import' && !$input->getOption('apply'))) {
                    $result = $this->migration->preview($id);
                } elseif ($action === 'import') {
                    $result = $this->migration->import($id, (string)$input->getOption('token'), (string)$input->getOption('backup-reference'));
                } elseif (in_array($action, ['activate', 'rollback'], true)) {
                    $result = $input->getOption('apply')
                        ? $this->migration->transition($id, $action, (string)$input->getOption('token'))
                        : $this->migration->review($id, $action);
                } else {
                    throw new \DomainException('Unknown action. Use list, preview, import, activate or rollback.');
                }
            }
            $output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), OutputInterface::OUTPUT_RAW);
            return 0;
        } catch (\DomainException $e) {
            $output->writeln($e->getMessage(), OutputInterface::OUTPUT_RAW);
        } catch (\Throwable $e) {
            // Adapter exceptions may include SQL parameters, including encrypted credentials.
            $output->writeln('Migration failed. Review receipts before retrying; check schema compatibility and database connectivity.', OutputInterface::OUTPUT_RAW);
        }
        return 1;
    }
}
