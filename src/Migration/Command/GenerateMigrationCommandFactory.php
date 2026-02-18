<?php

declare(strict_types=1);

namespace Marshal\Database\Migration\Command;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

final class GenerateMigrationCommandFactory
{
    public function __invoke(ContainerInterface $container): GenerateMigrationCommand
    {
        $dispatcher = $container->get(EventDispatcherInterface::class);
        return new GenerateMigrationCommand($dispatcher);
    }
}
