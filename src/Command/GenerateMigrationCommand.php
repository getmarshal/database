<?php

declare(strict_types=1);

namespace Marshal\Database\Command;

use Marshal\Database\ConfigProvider;
use Marshal\Database\DatabaseManager;
use Marshal\Database\Event\GenerateMigrationEvent;
use Marshal\Database\Event\SaveMigrationEvent;
use Marshal\Database\Repository\MigrationRepository;
use Marshal\Utils\Trait\CommandInputValidatorTrait;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GenerateMigrationCommand extends Command
{
    use CommandInputValidatorTrait;

    public const string COMMAND_NAME = "database:generate-migration";

    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct(self::COMMAND_NAME);
    }

    public function configure(): void
    {
        $this->addOption('database', 'd', InputOption::VALUE_REQUIRED, 'The database to generate migrations for');
        $this->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'The type to generate migrations for');
        $this->setDescription(
            "Generate and save statements to migrate a database or type to conform to it's schema specification"
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        // validate the input
        $this->validateInput($input);

        // prepare arguments
        $io = new SymfonyStyle($input, $output);
        $database = $input->getOption('database');
        $event = new GenerateMigrationEvent($database);
        if ($input->hasOption('type')) {
            $event->setTypeIdentifier($input->getOption('type'));
        }

        // generate the migration migration
        try {
            $this->eventDispatcher->dispatch($event);
            $diff = $event->getSchemaDiff();
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        if ($diff->isEmpty()) {
            $io->info("No schema changes to migrate");
            return Command::SUCCESS;
        }

        // print statements
        $connection = DatabaseManager::getConnection($database);
        $statements = $connection->getDatabasePlatform()->getAlterSchemaSQL($diff);
        $io->info("This migration will generate the following statements:");
        $io->info($statements);
        $save = $io->ask("Save this migration? y/n");
        if ('y' !== $save) {
            $io->info("Migration aborted");
            return Command::SUCCESS;
        }

        $name = $io->ask("Enter a name for this migration");
        if (empty($name)) {
            $io->error("Migration name cannot be empty");
            return Command::FAILURE;
        }

        if (true === MigrationRepository::nameExists($name)) {
            $io->error(\sprintf(
                "Migration with name %s already exists. Use a different name",
                $name
            ));
            return Command::FAILURE;
        }

        // save the migration
        $migration = MigrationRepository::save([
            ConfigProvider::MIGRATION_NAME => $name,
            ConfigProvider::MIGRATION_DATABASE => $database,
            ConfigProvider::MIGRATION_DIFF => \serialize($diff),
        ]);
        if ($migration->isEmpty()) {
            $io->error("Could not save migration");
            return Command::FAILURE;
        }

        $io->success("Migration $name generated");
        return Command::SUCCESS;
    }
}
