<?php

declare(strict_types=1);

namespace Marshal\Utils\Database\Migration;

use Marshal\Utils\Database\DatabaseAwareInterface;
use Marshal\Utils\Database\DatabaseAwareTrait;
use Marshal\Utils\Database\Schema\SchemaManager;
use Marshal\Utils\Database\Schema\Type;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class MigrationSetupCommand extends Command implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;
    use MigrationCommandTrait;

    public function __construct(protected ContainerInterface $container, string $name)
    {
        parent::__construct($name);
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
            $connection = $this->getDatabaseConnection();
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
        $typeManager = $this->container->get(SchemaManager::class);
        \assert($typeManager instanceof SchemaManager);

        $type = $typeManager->get("marshal::migration");
        \assert($type instanceof Type);

        $schema = $this->buildContentSchema([$type]);
        foreach ($schema->toSql($connection->getDatabasePlatform()) as $createStmt) {
            $connection->executeStatement($createStmt);
        }

        $io->success("Migration table setup");

        return Command::SUCCESS;
    }
}
