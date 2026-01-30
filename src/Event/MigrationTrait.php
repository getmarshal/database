<?php

declare(strict_types=1);

namespace Marshal\Database\Event;

use Doctrine\DBAL\Schema\SchemaDiff;
use Marshal\Database\ConfigProvider;
use Marshal\Database\Schema\Type;

trait MigrationTrait
{
    public function getMigrationDatabase(Type $migration): string
    {
        return $migration->getProperty(ConfigProvider::MIGRATION_DATABASE)->getValue();
    }

    public function getMigrationDiff(Type $migration): SchemaDiff
    {
        $result = $migration->getProperty(ConfigProvider::MIGRATION_DIFF)->getValue();
        $diff = \unserialize($result);
        if (! $diff instanceof SchemaDiff) {
            throw new \RuntimeException(\sprintf(
                "Could not unserialize diff for migration %s",
                $this->getMigrationName($migration)
            ));
        }

        return $diff;
    }

    public function getMigrationName(Type $migration): string
    {
        return $migration->getProperty(ConfigProvider::MIGRATION_NAME)->getValue();
    }
}
