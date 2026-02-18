<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Operator;

use Marshal\Database\QueryBuilder;
use Marshal\Database\Schema\Property;

final class Raw implements OperatorInterface
{
    public function __invoke(
        QueryBuilder $queryBuilder,
        Property $property,
        string $column
    ): void {
    }
}
