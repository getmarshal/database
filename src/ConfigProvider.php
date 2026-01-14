<?php

declare(strict_types=1);

namespace Marshal\Database;

final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            "commands" => $this->getCommands(),
            "database_expressions" => $this->getExpressions(),
            "dependencies" => $this->getDependencies(),
            "events" => $this->getEventListeners(),
            "filters" => [],
            "input_filters" => [],
            "validators" => [],
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

    private function getExpressions(): array
    {
        return [
            "where" => [
                \Marshal\Database\Query::WHERE_EQ => Query\Operator\Eq::class,
                \Marshal\Database\Query::WHERE_GT => Query\Operator\Gt::class,
                \Marshal\Database\Query::WHERE_GTE => Query\Operator\Gte::class,
                \Marshal\Database\Query::WHERE_INARRAY => Query\Operator\InArray::class,
                \Marshal\Database\Query::WHERE_ISNULL => Query\Operator\IsNull::class,
                \Marshal\Database\Query::WHERE_LT => Query\Operator\Lt::class,
                \Marshal\Database\Query::WHERE_LTE => Query\Operator\Lte::class,
                \Marshal\Database\Query::WHERE_RAW => Query\Operator\Raw::class,
            ],
        ];
    }
}
