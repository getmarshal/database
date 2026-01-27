<?php

declare(strict_types= 1);

namespace Marshal\Database\Command;

use Marshal\Database\ConfigProvider;
use Marshal\Database\DatabaseManager;
use Marshal\Database\Query;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class MigrationStatusCommand extends Command
{
    public const string COMMAND_NAME = "database:migration:status";

    public function __construct()
    {
        parent::__construct(self::COMMAND_NAME);
    }

    public function configure(): void
    {
        $this->setDescription('View the status of database schema migrations');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->info("Checking migration status...");

        try {
            $connection = DatabaseManager::getConnection();
        } catch (\Throwable $e) {
            $io->success("messages");
            return Command::FAILURE;
        }

        if (! $connection->createSchemaManager()->tableExists('migration')) {
            // @todo call migration:setup
            $io->error("Migrations NOT setup");
            return Command::FAILURE;
        }

        // fetch all migrations
        $data = Query::from(ConfigProvider::MIGRATION_TYPE)
            ->orderBy(ConfigProvider::MIGRATION_CREATED_AT, 'DESC')
            ->fetchAll();        
        if (empty($data)) {
            $io->success("No pending migrations");
            return Command::SUCCESS;
        }

        $result = [];
        foreach ($data as $row) {
            $row['status'] = $row['status'] == 1
                ? 'Done'
                : 'Pending';

            $result[] = [
                'migration' => $row['name'],
                'database' => $row['db'],
                'status' => $row['status'],
                'created' => $row['created_at']->format('c'),
                'executed' => $row['updated_at'] ? $row['updatedat']->format('c') : null,
            ];
        }

        // display status table
        $io->table(['Migration', 'Database', 'Status', 'Created', 'Executed'], $result);

        return Command::SUCCESS;
    }
}
