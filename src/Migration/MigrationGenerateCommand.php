<?php

declare(strict_types=1);

namespace Marshal\Utils\Database\Migration;

use Doctrine\DBAL\Schema\SchemaDiff;
use Marshal\Utils\Database\DatabaseAwareInterface;
use Marshal\Utils\Database\DatabaseAwareTrait;
use Marshal\Utils\Database\Schema\SchemaManager;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrationGenerateCommand extends Command implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;
    use MigrationCommandTrait;

    public function __construct(protected ContainerInterface $container, string $name)
    {
        parent::__construct($name);
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

        // get migration
        try {
            $diff = $this->generateMigration($database);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        if ($diff->isEmpty()) {
            $io->info("No schema changes to migrate");
            return Command::SUCCESS;
        }

        // print statements
        $connection = $this->getDatabaseConnection($database);
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

        // save migration
        $queryBuilder = $this->getDatabaseConnection()->createQueryBuilder();
        $save = $queryBuilder->insert('migration')
            ->setValue('name', $queryBuilder->createNamedParameter($normalizedName))
            ->setValue('db', $queryBuilder->createNamedParameter($database))
            ->setValue('diff', $queryBuilder->createNamedParameter(\serialize($diff)))
            ->setValue('created_at', $queryBuilder->createNamedParameter(new \DateTime))
            ->executeStatement();
        if (empty($save)) {
            $io->error("Could not save migration");
            return Command::FAILURE;
        }

        $io->success("Migration $normalizedName generated");
        return Command::SUCCESS;
    }

    private function generateMigration(string $database): SchemaDiff
    {
        // gather the definitions
        $definitions = [];
        $config =  $this->container->get('config')['schema']['types'] ?? [];
        $typeManager = $this->container->get(SchemaManager::class);
        \assert($typeManager instanceof SchemaManager);

        foreach (\array_keys($config) as $name) {
            $nameSplit = \explode('::', $name);
            if ($nameSplit[0] !== $database) {
                continue;
            }

            $definitions[] = $typeManager->get($name);
        }

        // generate the schema diff
        $dbalSchema = $this->getDatabaseConnection($database)->createSchemaManager();
        $fromSchema = $dbalSchema->introspectSchema();
        $toSchema = $this->buildContentSchema($definitions);
        return $dbalSchema->createComparator()->compareSchemas($fromSchema, $toSchema);
    }

    private function normalizeMigrationName(string $name): string
    {
        $replaced = \str_replace(' ', '_', $name);
        $timestamp = (new \DateTime())->format('Y-m-d-H-i-s');
        return "$timestamp-$replaced";
    }
}
