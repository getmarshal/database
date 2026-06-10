<?php

declare(strict_types=1);

namespace Marshal\Database;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;
use Marshal\Database\Middleware\HighPerfSqlite;
use Marshal\Database\Schema\ContentManager;
use Marshal\Utils\Config;
use Psr\EventDispatcher\EventDispatcherInterface;

final class DatabaseManager
{
    private static array $connections = [];
    private static ?EventDispatcherInterface $eventDispatcher = null;

    private function __construct()
    {
    }

    private function __clone(): void
    {
    }

    public static function getConnection(string $database = "marshal::main"): Connection
    {
        if (isset(self::$connections[$database])) {
            return self::$connections[$database];
        }

        $config = Config::get('database');
        if (! isset($config[$database])) {
            if (! \class_exists($database)) {
                throw new \InvalidArgumentException(\sprintf(
                    "Database connection %s not found in config",
                    $database
                ));
            }

            try {
                $content = ContentManager::get($database);
            } catch (\Throwable $e) {
                throw new \InvalidArgumentException(\sprintf(
                    "Database connection %s not found in config",
                    $database
                ));
            }

            if (! isset($config[$content->getContentConfig()->getDatabase()])) {
                throw new \InvalidArgumentException(\sprintf(
                    "Database connection %s not found in config",
                    $database
                ));
            }

            $database = $content->getContentConfig()->getDatabase();
        }

        // @todo validate db config

        // first time engaging a sqlite db?
        $middlewares = [];
        $firstConnect = false;
        if ($config[$database]['driver'] === "pdo_sqlite") {
            if (isset($config[$database]['path'])) {
                if (! \file_exists($config[$database]['path'])) {
                    $firstConnect = true;
                    $middlewares[] = new HighPerfSqlite();
                }
            }
        }

        $dbalConfig = new Configuration();
        $dbalConfig->setMiddlewares($middlewares);

        // wrap the connection
        $config[$database]["wrapperClass"] = Connection::class;

        // get the connection
        $connection = DriverManager::getConnection($config[$database], $dbalConfig);
        \assert($connection instanceof Connection);

        if (null !== self::$eventDispatcher) {
            $connection->setEventDispatcher(self::$eventDispatcher);
        }

        // @todo put pragma settings in config and allow override defaults
        // @todo wrap these in DBAL Driver middleware
        if (true === $firstConnect) {
            $connection->executeStatement("PRAGMA sychronous = NORMAL");
            $connection->executeStatement("PRAGMA journal_mode = WAL");
            $connection->executeStatement("PRAGMA cache_size = 10000");
            $connection->executeStatement("PRAGMA temp_store = MEMORY");
            $connection->executeStatement("PRAGMA mmap_size = 268435456");
        }

        // append to connections
        self::$connections[$database] = $connection;

        return $connection;
    }

    public static function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        self::$eventDispatcher = $eventDispatcher;
    }
}
