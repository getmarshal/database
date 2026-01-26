<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Trait;

use Marshal\Database\QueryBuilder;

trait GroupByTrait
{
    private array $groupBy = [];

    public function groupBy(): static
    {
        return $this;
    }

    private function applyGroupByExpressions(QueryBuilder $queryBuilder): void
    {
    }
}
