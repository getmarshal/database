<?php

declare(strict_types=1);

namespace Marshal\Database\Command;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

final class DatabaseMigrationRollbackCommandFactory
{
    public function __invoke(ContainerInterface $container): RollbackMigrationCommand
    {
        $dispatcher = $container->get(EventDispatcherInterface::class);
        return new RollbackMigrationCommand();
    }
}
