<?php

declare(strict_types=1);

namespace Marshal\Database\Command;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

final class DatabaseMigrationSetupCommandFactory
{
    public function __invoke(ContainerInterface $container): SetupMigrationsCommand
    {
        $dispatcher = $container->get(EventDispatcherInterface::class);
        return new SetupMigrationsCommand();
    }
}
