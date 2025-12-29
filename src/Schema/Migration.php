<?php

declare(strict_types=1);

namespace Marshal\Database\Schema;

use Marshal\Utils\Schema;

final class Migration
{
    public const string SCHEMA_NAME = "database::migration";
    public const string PROPERTY_DATABASE = "database::migration_db";
    public const string PROPERTY_DIFF = "database::migration_diff";
    public const string PROPERTY_STATUS = "database::migration_status";

    public function __invoke(): array
    {
        return [
            "schema" => [
                "properties" => [
                    self::PROPERTY_DATABASE => $this->getPropertyDatabase(),
                    self::PROPERTY_DIFF => $this->getPropertyDiff(),
                    self::PROPERTY_STATUS => $this->getPropertyStatus(),
                ],
                "types" => [
                    self::SCHEMA_NAME => $this->getSchema(),
                ],
            ],
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

    private function getSchema(): array
    {
        return [
            "database" => "marshal",
            "name" => "Migration",
            "description" => "Migrations table",
            "properties" => [
                Schema::PROPERTY_AUTO_ID,
                Schema::PROPERTY_NAME,
                self::PROPERTY_DATABASE,
                self::PROPERTY_DIFF,
                self::PROPERTY_STATUS,
                Schema::PROPERTY_UNIQUE_ALPHANUMERIC_TAG,
                Schema::PROPERTY_CREATED_AT,
                Schema::PROPERTY_UPDATED_AT,
            ],
            "table" => "migration",
        ];
    }
}
