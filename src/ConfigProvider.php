<?php

declare(strict_types=1);

namespace Marshal\Database;

final class ConfigProvider
{
    public const string DEFAULT_DATABASE = "database::default";

    public function __invoke(): array
    {
        return [
            "commands" => $this->getCommands(),
            "dependencies" => $this->getDependencies(),
            "events" => $this->getEventListeners(),
            "filters" => [],
            "input_filters" => [],
            "validators" => $this->getValidatorsConfig(),
        ];
    }

    private function getCommands(): array
    {
        return [
            Command\GenerateMigrationCommand::COMMAND_NAME => Command\GenerateMigrationCommand::class,
            Command\MigrationStatusCommand::COMMAND_NAME => Command\MigrationStatusCommand::class,
            Command\RollbackMigrationCommand::COMMAND_NAME => Command\RollbackMigrationCommand::class,
            Command\RunMigrationCommand::COMMAND_NAME => Command\RunMigrationCommand::class,
            Command\SetupMigrationsCommand::COMMAND_NAME => Command\SetupMigrationsCommand::class,
        ];
    }

    private function getDatabaseConfig(): array
    {
        return [
            self::DEFAULT_DATABASE => [
                "driver" => "pdo_sqlite",
                "driverOptions" => [
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                ],
                "path" => __DIR__ . "/../data/marshal.sqlite",
            ],
        ];
    }

    private function getDependencies(): array
    {
        return [
            "factories" => [
                Command\GenerateMigrationCommand::class => Command\GenerateMigrationCommandFactory::class,
                Command\RollbackMigrationCommand::class => Command\RollbackMigrationCommandFactory::class,
                Command\RunMigrationCommand::class => Command\RunMigrationCommandFactory::class,
                Command\SetupMigrationsCommand::class => Command\SetupMigrationsCommandFactory::class,
                Listener\MigrationEventsListener::class => Listener\MigrationEventsListenerFactory::class,
            ],
            "invokables" => [
                Command\MigrationStatusCommand::class => Command\MigrationStatusCommand::class,
            ],
        ];
    }

    private function getEventListeners(): array
    {
        return [
            "listeners" => [
                Listener\MigrationEventsListener::class => [
                    Event\GenerateMigrationEvent::class => [
                        "listener" => "onCreateMigrationEvent",
                    ],
                    Event\RollbackMigrationEvent::class => [
                        "listener" => "onRollbackMigrationEvent",
                    ],
                    Event\RunMigrationEvent::class => [
                        "listener" => "onRunMigrationEvent",
                    ],
                    Event\SetupMigrationsEvent::class => [
                        "listener" => "onSetupMigrationsEvent",
                    ],
                ],
            ],
        ];
    }

    private function getValidatorsConfig(): array
    {
        return [
            "factories" => [
                Validator\PropertyConfigValidator::class => Validator\PropertyConfigValidatorFactory::class,
                Validator\TypeConfigValidator::class => Validator\TypeConfigValidatorFactory::class,
            ],
        ];
    }
}
