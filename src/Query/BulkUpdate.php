<?php

declare(strict_types=1);

namespace Marshal\Database\Query;

use Marshal\Database\Query;
use Marshal\Database\QueryBuilder;

class BulkUpdate extends Query
{
    public function execute(): void
    {
        // @todo implement
    }

    public function prepare(): QueryBuilder
    {
        $queryBuilder = $this->getQueryBuilder();
        return $queryBuilder;
    }
}
