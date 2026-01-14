<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Operator;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;

final class IsNull implements OperatorInterface
{
    public static function applyOperation(
        QueryBuilder $queryBuilder,
        string $column,
        mixed $value,
        ParameterType $parameterType = ParameterType::STRING
    ): void {
        if (FALSE === $value) {
            $queryBuilder->andWhere($queryBuilder->expr()->isNotNull($column));
        } elseif (TRUE === $value) {
            $queryBuilder->andWhere($queryBuilder->expr()->isNull($column));
        }
    }
}
