<?php

declare(strict_types=1);

namespace Marshal\Database\Command;

use Marshal\Database\Event\GenerateMigrationEvent;
use Marshal\Database\Event\SaveMigrationEvent;
use Marshal\Utils\Database\DatabaseManager;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GenerateMigrationCommand extends Command
{
    public const string COMMAND_NAME = "migration:generate";

    public function __construct(private EventDispatcherInterface $eventDispatcher, private array $schemaConfig)
    {
        parent::__construct(self::COMMAND_NAME);
    }

    public function configure(): void
    {
        $this->addOption('database', 'd', InputOption::VALUE_REQUIRED, 'The database to generate migrations for');
        $this->setDescription(
            "Generate and save statements to migrate a database to conform to it's schema specification"
        );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $input->validate();

        $database = $input->getOption('database');
        $io = new SymfonyStyle($input, $output);

        // generate the migration migration
        $event = new GenerateMigrationEvent($database);
        try {
            $this->eventDispatcher->dispatch($event);
            $diff = $event->getSchemaDiff();
        } catch (\Throwable $e) {
            $io->error("Error generating migration");
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

        // normalize the name
        $normalizedName = $this->normalizeMigrationName($name);

        // save the migration
        $saveEvent = new SaveMigrationEvent($normalizedName, $database, $diff);
        $this->eventDispatcher->dispatch($saveEvent);
        if ($saveEvent->getIsSuccess() === FALSE) {
            $io->error("Could not save migration");
            return Command::FAILURE;
        }

        $io->success("Migration $normalizedName generated");
        return Command::SUCCESS;
    }

    private function normalizeMigrationName(string $name): string
    {
        $replaced = \str_replace(' ', '_', $name);
        $timestamp = (new \DateTime())->format('Y-m-d-H-i-s');
        return "$timestamp-$replaced";
    }
}
