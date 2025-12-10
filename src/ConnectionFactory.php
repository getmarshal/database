<?php

declare(strict_types=1);

namespace Marshal\Utils\Database;

use Doctrine\DBAL\DriverManager;

final class ConnectionFactory
{
    private array $connections = [];

    public function __construct(private array $config)
    {
    }

    public function getConnection(string $database = "marshal"): Connection
    {
        if (! \array_key_exists($database, $this->config)) {
            throw new \InvalidArgumentException(\sprintf(
                "Database connection %s not found in config",
                $database
            ));
        }

        // first time engaging a sqlite db?
        $firstConnect = false;
        if ($this->config[$database]['driver'] === "pdo_sqlite") {
            if (isset($this->config[$database]['path'])) {
                if (! file_exists($this->config[$database]['path'])) {
                    $firstConnect = true;
                }
            }
        }

        if (! \array_key_exists($database, $this->connections)) {
            // wrap the DBALConnection
            $this->config[$database]['wrapperClass'] = Connection::class;
            $connection = DriverManager::getConnection($this->config[$database]);
            
            // @todo put pragma settings in config and allow override defaults
            if (true === $firstConnect) {
                $connection->executeStatement("PRAGMA sychronous = NORMAL");
                $connection->executeStatement("PRAGMA journal_mode = WAL");
                $connection->executeStatement("PRAGMA cache_size = 10000");
                $connection->executeStatement("PRAGMA temp_store = MEMORY");
                $connection->executeStatement("PRAGMA mmap_size = 268435456");
            }

            $this->connections[$database] = $connection;
        }

        return $this->connections[$database];
    }
}
