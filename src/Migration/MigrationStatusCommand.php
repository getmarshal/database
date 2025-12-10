<?php

declare(strict_types= 1);

namespace Marshal\Utils\Database\Migration;

use Marshal\Utils\Database\DatabaseAwareInterface;
use Marshal\Utils\Database\DatabaseAwareTrait;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class MigrationStatusCommand extends Command implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    public function __construct(protected ContainerInterface $container, string $name)
    {
        parent::__construct($name);
    }

    public function configure(): void
    {
        $this->setDescription('View the status of database schema migrations');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info("Checking migration status...");

        // read migrations status
        try {
            $connection = $this->getDatabaseConnection();
        } catch (\Throwable $e) {
            $io->error("Error connecting to database");
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        if (! $connection->createSchemaManager()->tableExists('migration')) {
            // @todo call migration:setup
            $io->error("Migrations NOT setup");
            return Command::FAILURE;
        }

        $collection = $connection->createQueryBuilder()
            ->select('m.*')
            ->from('migration', 'm')
            ->orderBy('created_at', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();
        
        if (empty($collection)) {
            $io->success("No pending migrations");
            return Command::SUCCESS;
        }

        $result = [];
        foreach ($collection as $row) {
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
