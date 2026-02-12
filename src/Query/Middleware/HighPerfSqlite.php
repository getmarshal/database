<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Middleware;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

final class HighPerfSqlite implements Middleware
{
    public function wrap(Driver $driver): Driver
    {
        return $driver;
    }
}
