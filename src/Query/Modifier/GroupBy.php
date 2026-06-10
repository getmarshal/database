<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Modifier;

use Marshal\Database\QueryBuilder;

trait GroupBy
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
