<?php

declare(strict_types= 1);

namespace Marshal\Database\Command\Migration;

use Marshal\Database\Schema\Migration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class RollbackMigrationCommand extends Command
{
    public const string COMMAND_NAME = "migration:rollback";

    public function __construct()
    {
        parent::__construct(self::COMMAND_NAME);
    }

    public function configure(): void
    {
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'The name of the migration to rollback');
        $this->setDescription('Reverse one or more database migrations');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // validate the input
        $input->validate();

        // get details
        $name = $input->getOption('name');
        $migration = Migration::fetch($name);
        if ($migration->isEmpty()) {
            $io->error("Migration $name not found");
            return Command::FAILURE;
        }

        $diff = $migration->getMigrationDiff();

        // created tables
        foreach ($diff->getCreatedTables() as $table) {
            // @todo drop the table
        }

        foreach ($diff->getAlteredTables() as $tableDiff) {}

        foreach ($diff->getDroppedTables() as $table) {
            // @todo recreate the table
        }

        $io->success(\sprintf(
            "Migration %s successfully rolled back",
            $name
        ));

        return Command::SUCCESS;
    }
}
