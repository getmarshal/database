<?php

declare(strict_types=1);

namespace Marshal\Database\Handler;

use Psr\Container\ContainerInterface;

final class ContentSchemaTypeHandlerFactory
{
    public function __invoke(ContainerInterface $container): ContentSchemaTypeHandler
    {
        $databaseConfig = $container->get('config')['database'] ?? [];
        $schemaConfig = $container->get('config')['schema'] ?? [];
        return new ContentSchemaTypeHandler($container, $databaseConfig, $schemaConfig);
    }
}
