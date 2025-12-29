<?php

declare(strict_types= 1);

namespace Marshal\Database\Command;

use Doctrine\DBAL\Schema\SchemaDiff;
use Marshal\Database\DatabaseManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class RunMigrationCommand extends Command
{
    public const string COMMAND_NAME = "migration:run";

    public function __construct()
    {
        parent::__construct(self::COMMAND_NAME);
    }

    public function configure(): void
    {
        $this->addOption(
            name: "name",
            shortcut: null,
            mode: InputOption::VALUE_REQUIRED,
            description: "The name of the migration i.e it's config key"
        );
        $this->setDescription('Execute one or more pending migrations');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // validate the input
        $input->validate();

        // get details
        $name = $input->getOption('name');

        // get the migration
        $connection = DatabaseManager::getConnection();
        $queryBuilder = $connection->createQueryBuilder();
        $migration = $queryBuilder
            ->select('m.*')
            ->from('migration', 'm')
            ->where($queryBuilder->expr()->eq(
                'name',
                $queryBuilder->createNamedParameter($name)
            ))
            ->setMaxResults(1)
            ->executeQuery()
            ->fetchAssociative();

        if (empty($migration)) {
            $io->error("Migration $name not found");
            return Command::FAILURE;
        }

        $diff = \unserialize($migration['diff']);
        if (! $diff instanceof SchemaDiff) {
            $io->error("Invalid migration.");
            return Command::FAILURE;
        }

        try {
            $dbConnection = DatabaseManager::getConnection($migration['db']);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $migrationStatements = $dbConnection->getDatabasePlatform()->getAlterSchemaSQL($diff);
        $io->info($migrationStatements);
        $proceed = $io->ask("Proceed with this migration? y/n");
        if ($proceed !== 'y') {
            $io->info("Migration aborted");
            return Command::SUCCESS;
        }

        // update migration table
        $update = $queryBuilder->update('migration')
            ->set('status', $queryBuilder->createNamedParameter(1))
            ->set('updated_at', $queryBuilder->createNamedParameter((new \DateTime())->format('c')))
            ->where('id', $queryBuilder->createNamedParameter($migration['id']))
            ->executeStatement();
        if (empty($update)) {
            $io->error("An error occurred. Migration aborted");
            return Command::FAILURE;
        }

        // run the migration
        $failedStatements = [];
        $reasons = [];
        foreach ($migrationStatements as $statement) {
            try {
                $dbConnection->executeStatement($statement);
            } catch (\Throwable $e) {
                $failedStatements[] = $statement;
                $reasons[] = $e->getMessage();
                continue;
            }
        }

        if (! empty($failedStatements)) {
            $io->error("The following statements failed to execute");
            $io->error($failedStatements);
            $io->error($reasons);
        }

        $io->success(\sprintf(
            "Migration %s on database %s successfully run",
            $name, $migration['db']
        ));

        return Command::SUCCESS;
    }
}
