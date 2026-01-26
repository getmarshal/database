<?php

declare(strict_types=1);

namespace Marshal\Database\Query;

use Marshal\Database\Query;
use Marshal\Database\Query\Trait\WhereTrait;
use Marshal\Database\QueryBuilder;
use Marshal\Database\Schema\TypeManager;

final class Delete extends Query
{
    use WhereTrait;

    public function execute(): int|string
    {
        $query = $this->prepare();
        return $query->executeStatement();
    }

    public function from(string $schema): static
    {
        $this->type = TypeManager::get($schema);
        return $this;
    }

    protected function prepare(): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder->delete($this->type->getTable());
        $this->applyWhereExpressions($queryBuilder);

        return $queryBuilder;
    }
}