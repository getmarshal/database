<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Operator;

use Marshal\Database\QueryBuilder;
use Marshal\Database\Schema\Property;

class IsNull implements OperatorInterface
{
    public function __invoke(
        QueryBuilder $queryBuilder,
        Property $property,
        string $column
    ): void {
        if (FALSE === $property->getValue()) {
            $queryBuilder->andWhere($queryBuilder->expr()->isNotNull($column));
        } elseif (TRUE === $property->getValue()) {
            $queryBuilder->andWhere($queryBuilder->expr()->isNull($column));
        }
    }
}
