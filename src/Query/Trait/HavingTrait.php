<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Trait;

use Marshal\Database\QueryBuilder;

trait HavingTrait
{
    private array $having = [];

    public function having(): static
    {
        return $this;
    }

    private function applyHavingExpressions(QueryBuilder $queryBuilder): void
    {
    }
}
