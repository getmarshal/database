<?php

declare(strict_types=1);

namespace Marshal\Database\Event;

use Doctrine\DBAL\Schema\SchemaDiff;
use Marshal\Database\ConfigProvider;

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

    public function toArray(): array
    {
        return [
            ConfigProvider::PROPERTY_NAME => $this->getMigrationName(),
            ConfigProvider::PROPERTY_DATABASE => $this->getDatabase(),
            ConfigProvider::PROPERTY_DIFF => \serialize($this->getSchemaDiff()),
        ];
    }
}
