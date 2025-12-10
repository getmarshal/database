<?php

declare(strict_types=1);

namespace Marshal\Utils\Database;

trait DatabaseAwareTrait
{
    private ConnectionFactory $connectionFactory;

    public function getDatabaseConnection(string $name = "marshal"): Connection
    {
        return $this->connectionFactory->getConnection($name);
    }

    public function setDatabaseConnectionFactory(ConnectionFactory $connectionFactory): void
    {
        $this->connectionFactory = $connectionFactory;
    }
}
