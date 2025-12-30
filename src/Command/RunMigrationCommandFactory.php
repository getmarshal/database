<?php

declare(strict_types=1);

namespace Marshal\Database\Command;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

final class RunMigrationCommandFactory
{
    public function __invoke(ContainerInterface $container): RunMigrationCommand
    {
        $dispatcher = $container->get(EventDispatcherInterface::class);
        return new RunMigrationCommand();
    }
}
