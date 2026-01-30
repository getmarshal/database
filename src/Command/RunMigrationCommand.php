<?php

declare(strict_types= 1);

namespace Marshal\Database\Command;

use Marshal\Database\ConfigProvider;
use Marshal\Database\Event\MigrationTrait;
use Marshal\Database\Event\RunMigrationEvent;
use Marshal\Database\Repository\MigrationRepository;
use Marshal\Utils\Trait\CommandInputValidatorTrait;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class RunMigrationCommand extends Command
{
    use CommandInputValidatorTrait;
    use MigrationTrait;

    public const string COMMAND_NAME = "database:run-migration";

    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {
        parent::__construct(self::COMMAND_NAME);
    }

    public function configure(): void
    {
        $this->addOption(
            name: "name",
            shortcut: null,
            mode: InputOption::VALUE_REQUIRED,
            description: "The name of the migration"
        );
        $this->setDescription('Execute a pending migrations');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        // validate the input
        $this->validateInput($input);

        $io = new SymfonyStyle($input, $output);

        // get details
        $name = $input->getOption('name');

        // get the migration
        $migration = MigrationRepository::get($name);
        if ($migration->isEmpty()) {
            $io->error("Migration $name not found");
            return Command::FAILURE;
        }

        $event = new RunMigrationEvent($migration);
        try {
            $this->eventDispatcher->dispatch($event); 
        } catch (\Throwable $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        // update migration table
        MigrationRepository::updateMigrationOnCompletion($migration);

        $io->success(\sprintf(
            "Migration %s on database %s successfully run",
            $name, $this->getMigrationDatabase($migration)
        ));

        return Command::SUCCESS;
    }
}
