<?php

declare(strict_types=1);

namespace Marshal\Database\Handler;

use Psr\Container\ContainerInterface;

final class ContentSchemaHandlerFactory
{
    public function __invoke(ContainerInterface $container): ContentSchemaHandler
    {
        $databaseConfig = $container->get('config')['database'] ?? [];
        $schemaConfig = $container->get('config')['schema'] ?? [];
        return new ContentSchemaHandler($databaseConfig, $schemaConfig);
    }
}
