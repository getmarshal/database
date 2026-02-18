<?php

declare(strict_types=1);

namespace Marshal\Database\Query;

use Marshal\Database\Query;
use Marshal\Database\QueryBuilder;
use Marshal\Database\Schema\Type;
use Marshal\Database\Query\Trait\WhereTrait;

abstract class Delete extends Query
{
    use WhereTrait;

    abstract public static function from(string $target): static;
    abstract public static function target(object $target): int|string;

    public function __construct(private Type $type)
    {
    }

    public function execute(): int|string
    {
        $query = $this->prepare();
        return $query->executeStatement();
    }

    protected function prepare(): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder($this->type->getDatabase());
        $queryBuilder->delete($this->type->getTable());
        $this->applyWhereExpressions($queryBuilder, $this->type);

        return $queryBuilder;
    }
}