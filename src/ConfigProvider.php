<?php

declare(strict_types=1);

namespace Marshal\Database;

use Marshal\Utils\Random;

final class ConfigProvider
{
    public const string MIGRATION_TYPE = "database::migration";
    public const string MIGRATION_AUTO_ID = "database::migration-id";
    public const string MIGRATION_CREATED_AT = "database::migration-createdat";
    public const string MIGRATION_DATABASE = "database::migration-db";
    public const string MIGRATION_DIFF = "database::migration-diff";
    public const string MIGRATION_NAME = "database::migration-name";
    public const string MIGRATION_STATUS = "database::migration-status";
    public const string MIGRATION_TAG = "database::migration-tag";
    public const string MIGRATION_UPDATED_AT = "database::migration-updatedat";

    public function __invoke(): array
    {
        return [
            "commands" => $this->getCommands(),
            "database_expressions" => $this->getExpressions(),
            "dependencies" => $this->getDependencies(),
            "events" => $this->getEventListeners(),
            "filters" => [],
            "input_filters" => [],
            "schema" => [
                "properties" => [
                    self::MIGRATION_AUTO_ID => $this->getPropertyId(),
                    self::MIGRATION_CREATED_AT => $this->getPropertyCreatedAt(),
                    self::MIGRATION_DATABASE => $this->getPropertyDatabase(),
                    self::MIGRATION_DIFF => $this->getPropertyDiff(),
                    self::MIGRATION_NAME => $this->getPropertyName(),
                    self::MIGRATION_STATUS => $this->getPropertyStatus(),
                    self::MIGRATION_TAG => $this->getPropertyUniqueAlphaNumericTag(),
                    self::MIGRATION_UPDATED_AT => $this->getPropertyUpdatedAt(),
                ],
                "types" => [
                    self::MIGRATION_TYPE => $this->getMigrationSchema(),
                ],
            ],
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
            ],
        ];
    }

    private function getPropertyCreatedAt(): array
    {
        return [
            "label" => "Created At",
            "description" => "Entry creation time",
            "name" => "created_at",
            "type" => "datetimetz_immutable",
            "notnull" => true,
            "index" => true,
        ];
    }

    private function getPropertyDatabase(): array
    {
        return [
            "label" => "Migration DB",
            "description" => "Database name migration belongs to",
            "name" => "db",
            "index" => true,
            "length" => 255,
            "notnull" => true,
            "type" => "string",
        ];
    }

    private function getPropertyDiff(): array
    {
        return [
            "label" => "Migration Diff",
            "description" => "Serialized object containing a schema diff",
            "name" => "diff",
            "convertToPhpType" => false,
            "notnull" => true,
            "type" => "blob",
        ];
    }

    private function getPropertyId(): array
    {
        return [
            "autoincrement" => true,
            "description" => "Autoincrementing integer ID",
            "label" => "Auto ID",
            "name" => "id",
            "notnull" => true,
            "type" => "bigint",
        ];
    }

    private function getPropertyName(): array
    {
        return [
            "label" => "Name",
            "description" => "Entry name",
            "name" => "name",
            "notnull" => true,
            "type" => "string",
            "length" => 255,
        ];
    }

    private function getPropertyStatus(): array
    {
        return [
            "label" => "Migration Status",
            "description" => "0 or 1 migration status indicator",
            "name" => "status",
            'type' => 'smallint',
            'notnull' => true,
            'default' => 0,
            'index' => true,
        ];
    }

    private function getPropertyUniqueAlphaNumericTag(): array
    {
        return [
            "constraints" => [
                "unique" => true,
            ],
            "default" => static fn (): string => Random::generateTag(),
            "description" => "Entry unique alphanumeric identifier",
            "index" => true,
            "label" => "Unique Identifier",
            "length" => 255,
            "name" => "tag",
            "notnull" => true,
            "type" => "string",
        ];
    }

    private function getPropertyUpdatedAt(): array
    {
        return [
            "label" => "Updated At",
            "description" => "Entry last updated time",
            "name" => "updated_at",
            "type" => "datetimetz_immutable",
            "notnull" => true,
            "index" => true,
        ];
    }

    private function getMigrationSchema(): array
    {
        return [
            "database" => "marshal::main",
            "name" => "Migration",
            "description" => "Migrations table",
            "properties" => [
                self::MIGRATION_AUTO_ID,
                self::MIGRATION_NAME,
                self::MIGRATION_DATABASE,
                self::MIGRATION_DIFF,
                self::MIGRATION_STATUS,
                self::MIGRATION_TAG,
                self::MIGRATION_CREATED_AT,
                self::MIGRATION_UPDATED_AT,
            ],
            "table" => "migration",
        ];
    }
}
