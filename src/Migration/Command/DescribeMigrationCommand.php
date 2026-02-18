<?php

declare(strict_types=1);

namespace Marshal\Database\Migration\Command;

use Marshal\Database\DatabaseManager;
use Marshal\Database\Migration\Event\MigrationTrait;
use Marshal\Database\Migration\Repository\MigrationRepository;
use Marshal\Utils\Trait\CommandInputValidatorTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class DescribeMigrationCommand extends Command
{
    use CommandInputValidatorTrait;
    use MigrationTrait;

    public const string COMMAND_NAME = "database:describe-migration";

    public function __construct()
    {
        parent::__construct(self::COMMAND_NAME);
    }

    public function configure(): void
    {
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Name of migration');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        // validate the input
        $this->validateInput($input);

        $io = new SymfonyStyle($input, $output);

        // get the migration
        $name = $input->getOption('name');
        $migration = MigrationRepository::get($name);
        if ($migration->isEmpty()) {
            $io->error(\sprintf("Migration %s not found", $name));
            return Command::FAILURE;
        }
        
        // get the diff
        $diff = $this->getMigrationDiff($migration);

        // get the statements
        $connection = DatabaseManager::getConnection($migration->getDatabase());
        $statements = $connection->getDatabasePlatform()->getAlterSchemaSQL($diff);

        // display the statements
        $io->info("Database: {$migration->getDatabase()}");
        $io->info($statements);

        return Command::SUCCESS;
    }
}
