<?php

declare(strict_types=1);

namespace Marshal\Database\Event\Content;

use Marshal\Database\QueryBuilder;

trait QueryEventTrait
{
    public function getQuery(): QueryBuilder
    {
        return $this->queryBuilder;
    }
}
