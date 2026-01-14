<?php

declare(strict_types=1);

namespace Marshal\Database;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\QueryBuilder as DBALQueryBuilder;

final class QueryBuilder extends DBALQueryBuilder
{
    private array $where = [];

    public function __construct(private readonly Connection $connection)
    {
        parent::__construct($connection);
    }

    public function addExpression(string $expression, string $expressionClass): void
    {
        $this->where[$expression] = $expressionClass;
    }

    public function getDatabasePlatform(): AbstractPlatform
    {
        return $this->connection->getDatabasePlatform();
    }

    public function getExpression(string $name): string
    {
        if (! isset($this->where[$name])) {
            throw new \InvalidArgumentException("Expression $name not found");
        }

        return $this->where[$name];
    }
}
