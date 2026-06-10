<?php

declare(strict_types=1);

namespace Marshal\Database\Handler;

use Psr\Container\ContainerInterface;

final class ContentDashboardFactory
{
    public function __invoke(ContainerInterface $container): ContentDashboard
    {
        $databasesConfig = $container->get('config')['database'] ?? [];
        return new ContentDashboard($databasesConfig);
    }
}
