<?php

declare(strict_types= 1);

namespace Marshal\Database\Migration\Command;

use Marshal\Database\DatabaseManager;
use Marshal\Database\Migration\MigrationItem;
use Marshal\Database\Migration\Repository\MigrationRepository;
use Marshal\Database\Query\Hydrator\ItemInputHydrator;
use Marshal\Database\Schema\TypeManager;
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
        $data = MigrationRepository::getMigrations();
        if (empty($data)) {
            $io->success("No pending migrations");
            return Command::SUCCESS;
        }

        $result = [];
        foreach ($data as $row) {
            $status = $row['migration__status'] == true || $row['migration__status'] === 1
                ? 'Done'
                : 'Pending';

            $result[] = [
                'migration' => $row['migration__name'],
                'database' => $row['migration__db'],
                'status' => $status,
                'created' => $row['migration__created_at'],
                'executed' => $row['migration__updated_at'],
            ];
        }

        // display status table
        $io->table(['Migration', 'Database', 'Status', 'Created', 'Executed'], $result);

        return Command::SUCCESS;
    }
}
