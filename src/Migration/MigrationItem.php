<?php

declare(strict_types=1);

namespace Marshal\Database\Migration;

use Marshal\Database\Schema\Type;

final class MigrationItem
{
    public const string MIGRATION_ID = "database::migration-id";
    public const string MIGRATION_NAME = "database:migration-name";
    public const string MIGRATION_DATABASE = "database::migration-db";
    public const string MIGRATION_DIFF = "database::migration-diff";
    public const string MIGRATION_STATUS = "database::migration-status";
    public const string MIGRATION_TAG = "database::migration-tag";
    public const string MIGRATION_CREATEDAT = "database::migration-createdat";
    public const string MIGRATION_UPDATEDAT = "database::migration-updatedat";

    public function __construct(private Type $type)
    {
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->type->getProperty(self::MIGRATION_CREATEDAT)->getValue();
    }

    public function getDatabase(): string
    {
        return $this->type->getProperty(self::MIGRATION_DATABASE)->getValue();
    }

    public function getDiff(): string
    {
        return $this->type->getProperty(self::MIGRATION_DIFF)->getValue();
    }

    public function getName(): ?string
    {
        return $this->type->getProperty(self::MIGRATION_NAME)->getValue();
    }

    public function getStatus(): ?bool
    {
        return $this->type->getProperty(self::MIGRATION_STATUS)->getValue();
    }

    public function getTag(): string
    {
        return $this->type->getProperty(self::MIGRATION_TAG)->getValue();
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->type->getProperty(self::MIGRATION_UPDATEDAT)->getValue();
    }

    public function isEmpty(): bool
    {
        return null === $this->getType()->getAutoIncrement()->getValue() ? true : false;
    }
}
