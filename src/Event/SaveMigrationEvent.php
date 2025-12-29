<?php

declare(strict_types=1);

namespace Marshal\Database\Event;

use Doctrine\DBAL\Schema\SchemaDiff;

final class SaveMigrationEvent
{
    private bool $isSuccess = FALSE;

    public function __construct(
        private readonly string $name,
        private readonly string $database,
        private readonly SchemaDiff $diff
    ) {
    }

    public function getIsSuccess(): bool
    {
        return $this->isSuccess;
    }

    public function getDatabase(): string
    {
        return $this->database;
    }

    public function getSchemaDiff(): SchemaDiff
    {
        return $this->diff;
    }

    public function getMigrationName(): string
    {
        return $this->name;
    }

    public function setIsSuccess(bool $isSuccess): void
    {
        $this->isSuccess = $isSuccess;
    }
}
