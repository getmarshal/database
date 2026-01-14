<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Operator;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;

final class Raw implements OperatorInterface
{
    public static function applyOperation(
        QueryBuilder $queryBuilder,
        string $column,
        mixed $value,
        ParameterType $parameterType = ParameterType::STRING
    ): void {
        $queryBuilder->andWhere($column);
        if (\is_array($value)) {
            foreach ($value as $k => $v) {
                $queryBuilder->setParameter($k, $v);
            }
        }
    }
}
