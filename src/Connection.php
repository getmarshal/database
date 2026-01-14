<?php

declare(strict_types=1);

namespace Marshal\Database;

use Doctrine\DBAL\Connection as DBALConnection;

class Connection extends DBALConnection
{
    public function createQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this);
    }
}
