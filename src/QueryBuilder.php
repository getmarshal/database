<?php

declare(strict_types=1);

namespace Marshal\Database;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\QueryBuilder as DBALQueryBuilder;

final class QueryBuilder extends DBALQueryBuilder
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct($connection);
    }

    public function getDatabasePlatform(): AbstractPlatform
    {
        return $this->connection->getDatabasePlatform();
    }
}
