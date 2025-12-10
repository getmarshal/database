<?php

declare(strict_types=1);

namespace Marshal\Utils\Database;

use Psr\Container\ContainerInterface;

final class DatabaseAwareDelegatorFactory
{
    public function __invoke(ContainerInterface $container, string $requestedName, callable $callback): object
    {
        $instance = $callback();
        if (! $instance instanceof DatabaseAwareInterface) {
            throw new \InvalidArgumentException(\sprintf(
                "%s instance must implement %s",
                \get_debug_type($instance),
                DatabaseAwareInterface::class
            ));
        }

        $config = $container->get('config')['database'] ?? [];
        if (! \is_array($config) || empty($config)) {
            throw new \InvalidArgumentException(
                "Database configuration not found or is empty"
            );
        }

        $connectionFactory = new ConnectionFactory($config);
        $instance->setDatabaseConnectionFactory($connectionFactory);
        return $instance;
    }
}
