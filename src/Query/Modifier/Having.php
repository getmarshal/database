<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Modifier;

use Marshal\Database\QueryBuilder;

trait Having
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
