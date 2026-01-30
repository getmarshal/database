<?php

declare(strict_types=1);

namespace Marshal\Database\Event;

use Marshal\Database\Schema\Type;

class RunMigrationEvent
{
    public function __construct(private Type $migration)
    {
    }

    public function getMigration(): Type
    {
        return $this->migration;
    }
}
