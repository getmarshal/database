<?php

/* 
Copyright (C) 2026 Collins Pamba

This file is part of Marshal and Marshal is free software:
you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation,
either version 3 of the License, or (at your option) any later version.
*/

declare(strict_types=1);

namespace Marshal\Database;

use Doctrine\DBAL\Types\Types;
use Marshal\Utils\Random;

final class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            "commands" => $this->getCommandsConfig(),
            "dependencies" => $this->getDependenciesConfig(),
            "database_expressions" => $this->getExpressions(),
            "events" => $this->getEventsConfig(),
            "filters" => [],
            "input_filters" => [],
            "messages" => $this->getMessagesConfig(),
            "schema" => $this->getSchemaConfig(),
            "validators" => [],
        ];
    }

    private function getCommandsConfig(): array
    {
        return [
            Migration\Command\DescribeMigrationCommand::COMMAND_NAME => Migration\Command\DescribeMigrationCommand::class,
            Migration\Command\GenerateMigrationCommand::COMMAND_NAME => Migration\Command\GenerateMigrationCommand::class,
            Migration\Command\MigrationStatusCommand::COMMAND_NAME => Migration\Command\MigrationStatusCommand::class,
            Migration\Command\RollbackMigrationCommand::COMMAND_NAME => Migration\Command\RollbackMigrationCommand::class,
            Migration\Command\RunMigrationCommand::COMMAND_NAME => Migration\Command\RunMigrationCommand::class,
            Migration\Command\SetupMigrationsCommand::COMMAND_NAME => Migration\Command\SetupMigrationsCommand::class,
        ];
    }

    private function getDependenciesConfig(): array
    {
        return [
            "factories" => [
                Migration\Command\GenerateMigrationCommand::class => Migration\Command\GenerateMigrationCommandFactory::class,
                Migration\Command\RollbackMigrationCommand::class => Migration\Command\RollbackMigrationCommandFactory::class,
                Migration\Command\RunMigrationCommand::class => Migration\Command\RunMigrationCommandFactory::class,
                Migration\Command\SetupMigrationsCommand::class => Migration\Command\SetupMigrationsCommandFactory::class,
            ],
            "invokables" => [
                Migration\Command\DescribeMigrationCommand::class => Migration\Command\DescribeMigrationCommand::class,
                Migration\Command\MigrationStatusCommand::class => Migration\Command\MigrationStatusCommand::class,
                Migration\Listener\MigrationEventsListener::class => Migration\Listener\MigrationEventsListener::class,
            ],
        ];
    }

    private function getEventsConfig(): array
    {
        return [
            "listeners" => [
                Migration\Listener\MigrationEventsListener::class => [
                    Migration\Event\GenerateMigrationEvent::class => [
                        "listener" => "onGenerateMigrationEvent",
                    ],
                    Migration\Event\RollbackMigrationEvent::class => [
                        "listener" => "onRollbackMigrationEvent",
                    ],
                    Migration\Event\RunMigrationEvent::class => [
                        "listener" => "onRunMigrationEvent",
                    ],
                    Migration\Event\SetupMigrationsEvent::class => [
                        "listener" => "onSetupMigrationsEvent",
                    ],
                ],
            ],
        ];
    }

    private function getMessagesConfig(): array
    {
        return [];
    }

    private function getExpressions(): array
    {
        return [
            "where" => [
                QueryBuilder::WHERE_EQ => Query\Operator\Eq::class,
                QueryBuilder::WHERE_GT => Query\Operator\Gt::class,
                QueryBuilder::WHERE_GTE => Query\Operator\Gte::class,
                QueryBuilder::WHERE_INARRAY => Query\Operator\InArray::class,
                QueryBuilder::WHERE_ISNULL => Query\Operator\IsNull::class,
                QueryBuilder::WHERE_LT => Query\Operator\Lt::class,
                QueryBuilder::WHERE_LTE => Query\Operator\Lte::class,
                QueryBuilder::WHERE_NOT_INARRAY => Query\Operator\NotInArray::class,
            ],
        ];
    }

    private function getSchemaConfig(): array
    {
        return [
            "properties" => $this->getSchemaPropertiesConfig(),
            "types" => $this->getSchemaTypesConfig(),
        ];
    }

    private function getSchemaPropertiesConfig(): array
    {
        return [
            Migration\MigrationItem::MIGRATION_ID => [
                "autoincrement" => true,
                "description" => "Autoincrementing integer ID",
                "label" => "Migration ID",
                "name" => "id",
                "notnull" => true,
                "type" => Types::BIGINT,
            ],
            Migration\MigrationItem::MIGRATION_CREATEDAT => [
                "label" => "Migration Created At",
                "description" => "Migration creation timestamp",
                "default" => static fn (): \DateTimeImmutable => new \DateTimeImmutable(timezone: new \DateTimeZone('UTC')),
                "name" => "created_at",
                "type" => Types::DATETIMETZ_IMMUTABLE,
                "notnull" => true,
            ],
            Migration\MigrationItem::MIGRATION_DATABASE => [
                "label" => "Migration DB",
                "description" => "Database name migration belongs to",
                "name" => "db",
                "index" => true,
                "length" => 255,
                "notnull" => true,
                "type" => Types::STRING,
            ],
            Migration\MigrationItem::MIGRATION_DIFF => [
                "label" => "Migration Diff",
                "description" => "Serialized object containing a schema diff",
                "name" => "diff",
                "convertToPhpType" => false,
                "notnull" => true,
                "type" => Types::BLOB,
            ],
            Migration\MigrationItem::MIGRATION_NAME => [
                "label" => "Migration Name",
                "description" => "Given name for a migration",
                "name" => "name",
                "notnull" => true,
                "type" => Types::STRING,
                "length" => 255,
            ],
            Migration\MigrationItem::MIGRATION_STATUS => [
                "label" => "Migration Status",
                "description" => "Migration status indicator",
                "name" => "status",
                "type" => Types::BOOLEAN,
                "notnull" => true,
                "default" => false,
                "index" => true,
            ],
            Migration\MigrationItem::MIGRATION_TAG => [
                "constraints" => [
                    "unique" => true,
                ],
                "default" => static fn(): string => Random::generateTag(),
                "description" => "Unique tag for a migration",
                "index" => true,
                "label" => "Migration Tag",
                "name" => "tag",
                "notnull" => true,
                "type" => Types::STRING,
                "length" => 255,
            ],
            Migration\MigrationItem::MIGRATION_UPDATEDAT => [
                "label" => "Migration Updated At",
                "description" => "Migration updated at timestamp",
                "name" => "updated_at",
                "type" => Types::DATETIMETZ_IMMUTABLE,
            ],
        ];
    }

    private function getSchemaTypesConfig(): array
    {
        return [
            Migration\MigrationItem::class => [
                "database" => "marshal::migration",
                "name" => "Migration",
                "description" => "Migrations table",
                "properties" => [
                    Migration\MigrationItem::MIGRATION_ID,
                    Migration\MigrationItem::MIGRATION_NAME,
                    Migration\MigrationItem::MIGRATION_DATABASE,
                    Migration\MigrationItem::MIGRATION_DIFF,
                    Migration\MigrationItem::MIGRATION_STATUS,
                    Migration\MigrationItem::MIGRATION_TAG,
                    Migration\MigrationItem::MIGRATION_CREATEDAT,
                    Migration\MigrationItem::MIGRATION_UPDATEDAT,
                ],
                "table" => "migration",
            ],
        ];
    }
}
