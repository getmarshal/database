<?php

declare(strict_types=1);

namespace Marshal\Database\Migration\Event;

use Doctrine\DBAL\Schema\SchemaDiff;
use Marshal\Database\Migration\MigrationItem;

trait MigrationTrait
{
    public function getMigrationDiff(MigrationItem $migration): SchemaDiff
    {
        $diff = \unserialize($migration->getDiff());
        if (! $diff instanceof SchemaDiff) {
            throw new \RuntimeException(\sprintf(
                "Could not unserialize diff for migration %s",
                $migration->getName()
            ));
        }

        return $diff;
    }
}
