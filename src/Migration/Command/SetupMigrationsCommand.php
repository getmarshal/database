<?php

declare(strict_types=1);

namespace Marshal\Database\Migration\Command;

use Marshal\Database\DatabaseManager;
use Marshal\Database\Migration\Event\SetupMigrationsEvent;
use Marshal\Database\Migration\MigrationItem;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class SetupMigrationsCommand extends Command
{
    public const string COMMAND_NAME = "database:setup-migrations";

    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct(self::COMMAND_NAME);
    }

    public function configure(): void
    {
        $this->setDescription("Setup database migrations. Installs the migration table onto the main database");
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->info("Setting up migrations...");

        $connection = DatabaseManager::getConnection(MigrationItem::class);
        if ($connection->createSchemaManager()->tableExists('migration')) {
            $io->success("Migrations already setup!");
            return Command::SUCCESS;
        }

        $event = new SetupMigrationsEvent();
        $this->eventDispatcher->dispatch($event);
        if ($event->hasErrorMessages()) {
            $io->error($event->getErrorMessages());
            return Command::FAILURE;
        }

        $io->success("Migration table setup");
        return Command::SUCCESS;
    }
}
