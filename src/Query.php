<?php

declare(strict_types=1);

namespace Marshal\Database;

abstract class Query
{
    abstract protected function prepare(): QueryBuilder;

    public function getPreparedQuery(): QueryBuilder
    {
        return $this->prepare();
    }

    protected function createQueryBuilder(string $database): QueryBuilder
    {
        return DatabaseManager::getConnection($database)->createQueryBuilder();
    }
}
