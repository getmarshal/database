<?php

declare(strict_types=1);

namespace Marshal\Database\Command;

use Marshal\ContentManager\Schema\TypeManager;
use Marshal\Database\DatabaseManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class SetupMigrationsCommand extends Command
{
    public const string COMMAND_NAME = "migration:setup";

    public function __construct()
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

        try {
            $connection = DatabaseManager::getConnection();
        } catch (\Throwable $e) {
            $io->error("Error connecting to database");
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        if ($connection->createSchemaManager()->tableExists('migration')) {
            $io->info("Migrations already setup");
            return Command::SUCCESS;
        }

        // create the migrations table
        $type = TypeManager::get('marshal::migration');

        $schema = $this->buildContentSchema([$type]);
        foreach ($schema->toSql($connection->getDatabasePlatform()) as $createStmt) {
            $connection->executeStatement($createStmt);
        }

        $io->success("Migration table setup");

        return Command::SUCCESS;
    }
}
