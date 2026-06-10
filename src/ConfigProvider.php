<?php

/*
Copyright (C) 2026 Collins Pamba
*/

declare(strict_types=1);

namespace Marshal\Database;

use Doctrine\DBAL\Types\Types;
use Marshal\Utils\Random;
use Marshal\Database\Schema\Content;

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
            "navigation" => $this->getRoutesConfig(),
            "schema" => $this->getSchemaConfig(),
            "templates" => $this->getTemplates(),
            "validators" => [],
        ];
    }

    private function getCommandsConfig(): array
    {
        return [
            Command\Migration\DescribeMigrationCommand::COMMAND_NAME => Command\Migration\DescribeMigrationCommand::class,
            Command\Migration\GenerateMigrationCommand::COMMAND_NAME => Command\Migration\GenerateMigrationCommand::class,
            Command\Migration\MigrationStatusCommand::COMMAND_NAME => Command\Migration\MigrationStatusCommand::class,
            Command\Migration\RollbackMigrationCommand::COMMAND_NAME => Command\Migration\RollbackMigrationCommand::class,
            Command\Migration\RunMigrationCommand::COMMAND_NAME => Command\Migration\RunMigrationCommand::class,
            Command\Migration\SetupMigrationsCommand::COMMAND_NAME => Command\Migration\SetupMigrationsCommand::class,
        ];
    }

    private function getDependenciesConfig(): array
    {
        return [
            "factories" => [
                Command\Migration\GenerateMigrationCommand::class => Command\Migration\GenerateMigrationCommandFactory::class,
                Command\Migration\RollbackMigrationCommand::class => Command\Migration\RollbackMigrationCommandFactory::class,
                Command\Migration\RunMigrationCommand::class => Command\Migration\RunMigrationCommandFactory::class,
                Command\Migration\SetupMigrationsCommand::class => Command\Migration\SetupMigrationsCommandFactory::class,
                Handler\ContentDashboard::class => Handler\ContentDashboardFactory::class,
                Handler\ContentSchemaHandler::class                 => Handler\ContentSchemaHandlerFactory::class,
                Handler\ContentSchemaTypeHandler::class             => Handler\ContentSchemaTypeHandlerFactory::class,
            ],
            "invokables" => [
                Command\Migration\DescribeMigrationCommand::class => Command\Migration\DescribeMigrationCommand::class,
                Command\Migration\MigrationStatusCommand::class => Command\Migration\MigrationStatusCommand::class,
                Listener\MigrationEventsListener::class => Listener\MigrationEventsListener::class,
            ],
        ];
    }

    private function getEventsConfig(): array
    {
        return [
            "listeners" => [
                Listener\MigrationEventsListener::class => [
                    Event\Migration\GenerateMigrationEvent::class => [
                        "listener" => "onGenerateMigrationEvent",
                    ],
                    Event\Migration\RollbackMigrationEvent::class => [
                        "listener" => "onRollbackMigrationEvent",
                    ],
                    Event\Migration\RunMigrationEvent::class => [
                        "listener" => "onRunMigrationEvent",
                    ],
                    Event\Migration\SetupMigrationsEvent::class => [
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
                QueryBuilder::WHERE_IN_QUERY => Query\Operator\InQuery::class,
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
            Content::ID => [
                "autoincrement" => true,
                "description" => "Autoincrementing integer ID",
                "label" => "Auto ID",
                "name" => "id",
                "notnull" => true,
                "type" => "bigint",
            ],
            Content::NAME => [
                "label" => "Name",
                "description" => "Item name",
                "name" => "name",
                "notnull" => true,
                "type" => "string",
                "length" => 255,
                "filters" => [
                    \Laminas\Filter\ToString::class => [],
                ],
                "validators" => [
                    \Laminas\Validator\NotEmpty::class => [],
                    \Laminas\Validator\StringLength::class => [
                        'max' => 255,
                    ],
                ],
            ],
            Content::ALIAS => [
                "label" => "Alias",
                "description" => "Item alternate name",
                "name" => "alias",
                "type" => "string",
                "length" => 255,
            ],
            Content::DESCRIPTION => [
                "label" => "Description",
                "description" => "Item brief description",
                "name" => "description",
                "type" => "text",
            ],
            Content::URL => [
                "label" => "URL",
                "description" => "Item url",
                "name" => "url",
                "type" => "string",
                "length" => 255,
            ],
            Content::IMAGE => [
                "label" => "Image",
                "description" => "Item featured image",
                "name" => "image",
                "type" => "string",
                "length" => 255,
            ],
            Content::TAG => [
                "constraints" => [
                    "unique" => true,
                ],
                "default" => static fn(): string => Random::generateTag(),
                "description" => "A unique alphanumeric identifier",
                "index" => true,
                "label" => "Unique Alphanumeric Identifier",
                "length" => 255,
                "name" => "tag",
                "notnull" => true,
                "type" => "string",
                "filters" => [
                    \Laminas\Filter\ToString::class => [],
                    \Laminas\Filter\StringTrim::class => [],
                ],
                "validators" => [
                    \Laminas\Validator\NotEmpty::class => [],
                    \Laminas\Validator\StringLength::class => ["min" => 9]
                ],
            ],
            Content::CREATED_AT => [
                "label" => "Created At",
                "default" => static fn (): \DateTimeImmutable => new \DateTimeImmutable(timezone: new \DateTimeZone('UTC')),
                "description" => "Item creation time",
                "name" => "created_at",
                "type" => "datetimetz_immutable",
                "notnull" => true,
                "index" => true,
            ],
            Content::UPDATED_AT => [
                "label" => "Updated At",
                "default" => static fn (): \DateTimeImmutable => new \DateTimeImmutable(timezone: new \DateTimeZone('UTC')),
                "description" => "Item last updated time",
                "name" => "updated_at",
                "type" => "datetimetz_immutable",
                "index" => true,
            ],
            Schema\Migration::MIGRATION_DATABASE => [
                "label" => "Migration DB",
                "description" => "Database name migration belongs to",
                "name" => "db",
                "index" => true,
                "length" => 255,
                "notnull" => true,
                "type" => Types::STRING,
            ],
            Schema\Migration::MIGRATION_DIFF => [
                "label" => "Migration Diff",
                "description" => "Serialized object containing a schema diff",
                "name" => "diff",
                "convertToPhpType" => false,
                "notnull" => true,
                "type" => Types::BLOB,
            ],
            Schema\Migration::MIGRATION_STATUS => [
                "label" => "Migration Status",
                "description" => "Migration status indicator",
                "name" => "status",
                "type" => Types::BOOLEAN,
                "notnull" => true,
                "default" => false,
                "index" => true,
            ],
        ];
    }

    private function getSchemaTypesConfig(): array
    {
        return [
            Schema\Migration::class => [
                "database" => "marshal::migration",
                "name" => "Migration",
                "description" => "Migrations table",
                "properties" => [
                    Content::ID,
                    Content::NAME,
                    Schema\Migration::MIGRATION_DATABASE,
                    Schema\Migration::MIGRATION_DIFF,
                    Schema\Migration::MIGRATION_STATUS,
                    Content::TAG,
                    Content::CREATED_AT,
                    Content::UPDATED_AT,
                ],
                "table" => "migration",
            ],
        ];
    }

    private function getRoutesConfig(): array
    {
        return [
            "paths" => [
                "/content" => [
                    "name" => Handler\ContentDashboard::ROUTE_DASHBOARD,
                    "methods" => ["GET"],
                    "middleware" => Handler\ContentDashboard::class,
                    "options" => [
                        "template" => "marshal::content-dashboard",
                    ],
                ],
                "/content/{schema}" => [
                    "name" => Handler\ContentSchemaHandler::ROUTE_CONTENT_SCHEMA,
                    "methods" => ["GET"],
                    "middleware" => Handler\ContentSchemaHandler::class,
                    "options" => [
                        "template" => "marshal::content-schema",
                    ],
                ],
                "/content/{schema}/{type}" => [
                    "name" => Handler\ContentSchemaTypeHandler::ROUTE_CONTENT_SCHEMA_TYPE,
                    "methods" => ["GET", "POST"],
                    "middleware" => Handler\ContentSchemaTypeHandler::class,
                    "options" => [
                        "template" => "marshal::content-schema-type",
                    ],
                ],
            ],
        ];
    }

    private function getTemplates(): array
    {
        return [
            "marshal::content-dashboard" => [
                "filename" => "/main/content/dashboard.twig.html",
                "includes" => ["main::layout"],
            ],
            "marshal::content-schema" => [
                "filename" => "/main/content/schema.twig.html",
                "includes" => ["main::layout"],
            ],
            "marshal::content-schema-type" => [
                "filename" => "/main/content/schema-type.twig.html",
                "includes" => ["main::layout"],
            ],
        ];
    }
}
