<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Operator;

use Marshal\Database\QueryBuilder;
use Marshal\Database\Schema\Property;

class Lte implements OperatorInterface
{
    public function __invoke(
        QueryBuilder $queryBuilder,
        Property $property,
        string $column
    ): void {
        $queryBuilder->andWhere($queryBuilder->expr()->lte(
            $column,
            $queryBuilder->createNamedParameter(
                $property->convertToDatabaseValue($queryBuilder->getDatabasePlatform()),
                $property->getDatabaseType()->getBindingType()
            )
        ));
    }
}
