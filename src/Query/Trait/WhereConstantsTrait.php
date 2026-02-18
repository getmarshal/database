<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Trait;

use Marshal\Database\QueryBuilder;

trait WhereConstantsTrait
{
    final public const string WHERE_EQ = QueryBuilder::WHERE_EQ;
    final public const string WHERE_GT = QueryBuilder::WHERE_GT;
    final public const string WHERE_GTE = QueryBuilder::WHERE_GTE;
    final public const string WHERE_INARRAY = QueryBuilder::WHERE_INARRAY;
    final public const string WHERE_ISNULL = QueryBuilder::WHERE_ISNULL;
    final public const string WHERE_LT = QueryBuilder::WHERE_LT;
    final public const string WHERE_LTE = QueryBuilder::WHERE_LTE;
    final public const string WHERE_NOT_INARRAY = QueryBuilder::WHERE_NOT_INARRAY;
    final public const string WHERE_RAW = QueryBuilder::WHERE_RAW;
}
