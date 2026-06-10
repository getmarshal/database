<?php

declare(strict_types=1);

namespace Marshal\Database\Schema;

use Doctrine\DBAL\Schema\SchemaDiff;
use Marshal\Database\Query\Create;
use Marshal\Database\Query\Select;
use Marshal\Database\Query\Update;

final class Migration extends Content
{
    public const string MIGRATION_DATABASE = "database::migration-db";
    public const string MIGRATION_DIFF = "database::migration-diff";
    public const string MIGRATION_STATUS = "database::migration-status";

    public static function fetch(string $name): self
    {
        return Select::from(self::class)
            ->where(Content::NAME, $name)
            ->fetch();
    }

    public function getMigrationDatabase(): string
    {
        return $this->getProperty(self::MIGRATION_DATABASE)->getValue();
    }

    public function getDiff(): string
    {
        return $this->getProperty(self::MIGRATION_DIFF)->getValue();
    }

    public function getMigrationDiff(): SchemaDiff
    {
        $diff = \unserialize($this->getDiff());
        if (! $diff instanceof SchemaDiff) {
            throw new \RuntimeException(\sprintf(
                "Could not unserialize diff for migration %s",
                $this->getName()
            ));
        }

        return $diff;
    }

    public function getName(): ?string
    {
        return $this->getProperty(Content::NAME)->getValue();
    }

    public function getStatus(): ?bool
    {
        return $this->getProperty(self::MIGRATION_STATUS)->getValue();
    }

    public function isEmpty(): bool
    {
        return null === $this->getAutoIncrement()->getValue() ? true : false;
    }

    public function updateMigrationOnCompletion(): self
    {
        Update::target($this)->withValues([
            self::MIGRATION_STATUS => true,
            Content::UPDATED_AT => new \DateTimeImmutable(timezone: new \DateTimeZone('UTC')),
        ])->execute();

        return $this;
    }

    public static function getMigrations(): array
    {
        return Select::from(self::class)
            ->orderBy(Content::CREATED_AT, 'DESC')
            ->fetchAllAssociative();
    }

    public static function nameExists(string $name): bool
    {
        $migration = self::fetch($name);
        return $migration->isEmpty() ? false : true;
    }

    public static function save(array $input): self
    {
        return Create::fromArray(self::class, $input)->execute();
    }
}
