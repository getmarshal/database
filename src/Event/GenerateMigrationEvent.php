<?php

declare(strict_types=1);

namespace Marshal\Database\Event;

use Doctrine\DBAL\Schema\SchemaDiff;
use Doctrine\DBAL\Schema\TableDiff;

class GenerateMigrationEvent
{
    private SchemaDiff $diff;
    private ?string $typeIdentifier = null;
    
    public function __construct(private readonly string $database)
    {
    }

    public function getDatabase(): string
    {
        return $this->database;
    }

    public function getSchemaDiff(): SchemaDiff
    {
        if (! $this->diff instanceof SchemaDiff) {
            throw new \RuntimeException("Diff not set");
        }

        return $this->diff;
    }

    public function getTypeIdentifier(): string
    {
        if (null === $this->typeIdentifier) {
            throw new \RuntimeException(\sprintf(
                "Migration type identifier not found"
            ));
        }

        return $this->typeIdentifier;
    }

    public function hasTypeIdentifier(): bool
    {
        return null === $this->typeIdentifier ? false : true;
    }

    public function isTypeMigration(): bool
    {
        return $this->hasTypeIdentifier();
    }

    public function setDiff(SchemaDiff $diff): void
    {
        $this->diff = $diff;
    }

    public function setTableDiff(TableDiff $diff): void
    {
    }

    public function setTypeIdentifier(string $typeIdentifier): void
    {
        $this->typeIdentifier = $typeIdentifier;
    }
}
