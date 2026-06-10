<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Operator;

use Marshal\Database\Query\Select;
use Marshal\Database\QueryBuilder;
use Marshal\Database\Schema\Property;

class InQuery implements OperatorInterface
{
    public function __invoke(
        QueryBuilder $queryBuilder,
        Property $property,
        string $column
    ): void {
        $value = $property->getValue();
        if (! $value instanceof Select) {
            throw new \InvalidArgumentException(\sprintf(
                "Invalid operator %s value. Expected instance of %s, given %s instead",
                self::class,
                Select::class,
                \get_debug_type($value)
            ));
        }

        $subquery = $value->getPreparedQuery();
        $queryBuilder->andWhere($subquery->getSQL());
        foreach ($subquery->getParameters() as $parameter => $value) {
            $queryBuilder->setParameter($parameter, $value);
        }
    }
}
