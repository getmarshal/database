<?php

declare(strict_types=1);

namespace Marshal\Database;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\QueryBuilder as DBALQueryBuilder;

final class QueryBuilder extends DBALQueryBuilder
{
    public const string WHERE_EQ = "eq";
    public const string WHERE_GT = "gt";
    public const string WHERE_GTE = "gte";
    public const string WHERE_INARRAY = "inArray";
    public const string WHERE_ISNULL = "isNull";
    public const string WHERE_LT = "lt";
    public const string WHERE_LTE = "lte";
    public const string WHERE_NOT_INARRAY = "notInArray";
    public const string WHERE_RAW = "raw";
    
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct($connection);
    }

    public function getDatabasePlatform(): AbstractPlatform
    {
        return $this->connection->getDatabasePlatform();
    }

    public function lastInsertId(): int|string
    {
        return $this->connection->lastInsertId();
    }
}
