<?php

declare(strict_types=1);

namespace Marshal\Database\Event\Content;

use Marshal\Database\QueryBuilder;

final class DeleteQueryEvent
{
    use QueryEventTrait;

    public function __construct(private QueryBuilder $queryBuilder)
    {

    }
}
