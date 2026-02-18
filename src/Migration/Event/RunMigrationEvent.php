<?php

declare(strict_types=1);

namespace Marshal\Database\Migration\Event;

use Marshal\Database\Migration\MigrationItem;

class RunMigrationEvent
{
    public function __construct(private MigrationItem $migration)
    {
    }

    public function getMigration(): MigrationItem
    {
        return $this->migration;
    }
}
