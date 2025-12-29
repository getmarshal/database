<?php

declare(strict_types=1);

namespace Marshal\Database\Event;

use Doctrine\DBAL\Schema\SchemaDiff;

class GenerateMigrationEvent
{
    private SchemaDiff $diff;
    
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

    public function setDiff(SchemaDiff $diff): void
    {
        $this->diff = $diff;
    }
}
