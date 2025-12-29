<?php

declare(strict_types=1);

namespace Marshal\Database\Listener;

use Psr\Container\ContainerInterface;

final class MigrationEventsListenerFactory
{
    public function __invoke(ContainerInterface $container): MigrationEventsListener
    {
        $schemaConfig = $container->get('config')['schema'] ?? [];
        return new MigrationEventsListener($schemaConfig);
    }
}
