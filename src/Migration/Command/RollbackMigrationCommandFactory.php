<?php

declare(strict_types=1);

namespace Marshal\Database\Migration\Command;

use Psr\Container\ContainerInterface;

final class RollbackMigrationCommandFactory
{
    public function __invoke(ContainerInterface $container): RollbackMigrationCommand
    {
        return new RollbackMigrationCommand();
    }
}
