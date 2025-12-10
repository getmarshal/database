<?php

declare(strict_types=1);

namespace Marshal\Utils\Database;

interface DatabaseAwareInterface
{
    public function getDatabaseConnection(string $name = "marshal"): Connection;
    public function setDatabaseConnectionFactory(ConnectionFactory $connectionFactory): void;
}
