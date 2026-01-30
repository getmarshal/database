<?php

declare(strict_types=1);

namespace Marshal\Database\Repository;

use Marshal\Database\ConfigProvider;
use Marshal\Database\Query;
use Marshal\Database\Schema\Type;

final class MigrationRepository
{
    public static function get(string $name): Type
    {
        return Query::from(ConfigProvider::MIGRATION_TYPE)
            ->where(ConfigProvider::MIGRATION_NAME, $name)
            ->fetch();
    }

    public static function nameExists(string $name): bool
    {
        $migration = self::get($name);
        return $migration->isEmpty() ? false : true;
    }

    public static function save(array $input): Type
    {
        return Query::create(ConfigProvider::MIGRATION_TYPE)
            ->fromInput($input)
            ->execute();
    }

    public static function updateMigrationOnCompletion(Type $migration): Type
    {
        Query::update($migration)->withValues([
            ConfigProvider::MIGRATION_STATUS => 1,
            ConfigProvider::MIGRATION_UPDATED_AT => new \DateTimeImmutable(timezone: new \DateTimeZone('UTC')),
        ])->execute();
        return $migration;
    }
}
