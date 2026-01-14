<?php

declare(strict_types=1);

namespace Marshal\Database;

use Doctrine\DBAL\DriverManager;
use Marshal\Utils\Config;

final class DatabaseManager
{
    private static array $connections = [];

    private function __construct()
    {
    }

    private function __clone(): void
    {
    }

    public static function getConnection(string $database = "marshal"): Connection
    {
        if (isset(self::$connections[$database])) {
            return self::$connections[$database];
        }

        $config = Config::get('database');
        if (! isset($config[$database])) {
            throw new \InvalidArgumentException(\sprintf(
                "Database connection %s not found in config",
                $database
            ));
        }

        // @todo validate db config

        // first time engaging a sqlite db?
        $firstConnect = false;
        if ($config[$database]['driver'] === "pdo_sqlite") {
            if (isset($config[$database]['path'])) {
                if (! \file_exists($config[$database]['path'])) {
                    $firstConnect = true;
                }
            }
        }

        // wrap the connection
        if (! isset($config[$database]["wrapperClass"])) {
            $config[$database]["wrapperClass"] = Connection::class;
        }
        
        // get the connection
        $connection = DriverManager::getConnection($config[$database]);
        
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
}
