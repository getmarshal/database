<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Operator;

use Marshal\Database\Query\Trait\WhereTrait;
use Marshal\Database\QueryBuilder;
use Marshal\Database\Schema\Property;

class InArray implements OperatorInterface
{
    public function __invoke(
        QueryBuilder $queryBuilder,
        Property $property,
        string $column
    ): void {
        // @todo validate property value is list of strings
        $queryBuilder->andWhere($queryBuilder->expr()->in(
            $column,
            \array_map(
                static fn (string $property): string => "'$property'",
                $property->convertToDatabaseValue($queryBuilder->getDatabasePlatform())
            )
        ));
    }
}
