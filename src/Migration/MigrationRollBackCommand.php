<?php

declare(strict_types= 1);

namespace Marshal\Utils\Database\Migration;

use Doctrine\DBAL\Schema\SchemaDiff;
use Marshal\Utils\Database\DatabaseAwareInterface;
use Marshal\Utils\Database\DatabaseAwareTrait;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class MigrationRollBackCommand extends Command implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;
    use MigrationCommandTrait;

    public function __construct(protected ContainerInterface $container, string $name)
    {
        parent::__construct($name);
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
        $connection = $this->getDatabaseConnection();
        $queryBuilder = $connection->createQueryBuilder();
        $migration = $queryBuilder
            ->select('m.*')
            ->from('migration', 'm')
            ->where($queryBuilder->expr()->eq(
                'm.name',
                $queryBuilder->createNamedParameter($name)
            ))
            ->executeQuery()
            ->fetchAssociative();
        if (empty($migration)) {
            $io->error("Migration $name not found");
            return Command::FAILURE;
        }

        $diff = $migration['diff'];
        $database = $migration['db'];
        \assert($diff instanceof SchemaDiff);

        // created tables
        foreach ($diff->getCreatedTables() as $table) {
            // @todo delete the table
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
