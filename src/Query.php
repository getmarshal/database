<?php

declare(strict_types= 1);

namespace Marshal\Database;

use Marshal\Database\Query\BulkCreate;
use Marshal\Database\Query\BulkUpdate;
use Marshal\Database\Query\Create;
use Marshal\Database\Query\Delete;
use Marshal\Database\Query\Select;
use Marshal\Database\Query\Update;
use Marshal\Database\Schema\Type;
use Marshal\Database\Schema\TypeManager;

abstract class Query
{
    public const string WHERE_EQ = "eq";
    public const string WHERE_GT = "gt";
    public const string WHERE_GTE = "gte";
    public const string WHERE_INARRAY = "inArray";
    public const string WHERE_ISNULL = "isNull";
    public const string WHERE_LT = "lt";
    public const string WHERE_LTE = "lte";
    public const string WHERE_NOT_INARRAY = "notInArray";
    public const string WHERE_RAW = "raw";

    protected Type $type;

    abstract protected function prepare(): QueryBuilder;

    public function getPreparedQuery(): QueryBuilder
    {
        return $this->prepare();
    }

    public static function bulkCreate(): BulkCreate
    {
        return new BulkCreate;
    }

    public static function bulkUpdate(): BulkUpdate
    {
        return new BulkUpdate;
    }

    public static function create(string|Type $type): Create
    {
        if (\is_string($type)) {
            $type = TypeManager::get($type);
        }

        return new Create($type);
    }

    public static function delete(): Delete
    {
        return new Delete;
    }

    public static function select(): Select
    {
        return new Select;
    }

    public static function update(Type $type): Update
    {        
        return new Update($type);
    }

    protected function createQueryBuilder(): QueryBuilder
    {
        return DatabaseManager::getConnection($this->type->getDatabase())->createQueryBuilder();
    }

    protected function getQueryBuilder(): QueryBuilder
    {
        return DatabaseManager::getConnection($this->type->getDatabase())->createQueryBuilder();
    }
}
