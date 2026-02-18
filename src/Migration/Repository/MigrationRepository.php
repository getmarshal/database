<?php

declare(strict_types=1);

namespace Marshal\Database\Migration\Repository;

use Marshal\Database\Migration\MigrationItem;
use Marshal\Database\Query\Create;
use Marshal\Database\Query\Select;
use Marshal\Database\Query\Update;

final class MigrationRepository
{
    public static function get(string $name): MigrationItem
    {
        $type = Select::from(MigrationItem::class)
            ->where(MigrationItem::MIGRATION_NAME, $name)
            ->fetch();
        return new MigrationItem($type);
    }

    public static function getMigrations(): array
    {
        return Select::from(MigrationItem::class)
            ->orderBy(MigrationItem::MIGRATION_CREATEDAT, 'DESC')
            ->fetchAllAssociative();
    }

    public static function nameExists(string $name): bool
    {
        $migration = self::get($name);
        return null === $migration->getType()->getAutoIncrement()->getValue() ? true : false;
    }

    public static function save(array $input): MigrationItem
    {
        return Create::fromArray(MigrationItem::class, $input)
            ->execute();
    }

    public static function updateMigrationOnCompletion(MigrationItem $migration): MigrationItem
    {
        Update::target($migration)->withValues([
            MigrationItem::MIGRATION_STATUS => true,
            MigrationItem::MIGRATION_UPDATEDAT => new \DateTimeImmutable(timezone: new \DateTimeZone('UTC')),
        ])->execute();

        return $migration;
    }
}
