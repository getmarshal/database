<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Operator;

use Doctrine\DBAL\ParameterType;
use Marshal\Database\QueryBuilder;

final class Eq implements OperatorInterface
{
    public static function applyOperation(
        QueryBuilder $queryBuilder,
        string $column,
        mixed $value,
        ParameterType $parameterType = ParameterType::STRING
    ): void {
        $queryBuilder->andWhere($queryBuilder->expr()->eq(
            $column,
            $queryBuilder->createNamedParameter(
                $value,
                $parameterType
            )
        ));
    }
}
