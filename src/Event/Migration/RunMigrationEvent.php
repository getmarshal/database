<?php

declare(strict_types=1);

namespace Marshal\Database\Event\Migration;

use Marshal\Database\Schema\Migration;

class RunMigrationEvent
{
    public function __construct(private Migration $migration)
    {
    }

    public function getMigration(): Migration
    {
        return $this->migration;
    }
}
