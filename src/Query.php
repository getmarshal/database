<?php

declare(strict_types= 1);

namespace Marshal\Database;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use loophp\collection\Collection;
use Marshal\Database\Hydrator\TypeInputHydrator;
use Marshal\Utils\Logger\LoggerManager;

final class Query
{
    public const string MOD_EQ = "eq";
    public const string MOD_GT = "gt";
    public const string MOD_GTE = "gte";
    public const string MOD_IN = "in";
    public const string MOD_ISNOTNULL = "isNotNull";
    public const string MOD_ISNULL = "isNull";
    public const string MOD_LT = "lt";
    public const string MOD_LTE = "lte";
    public const string MOD_NOTIN = "notIn";
    public const string MOD_RAW = "raw";
    public const string JOIN_LEFT = "leftJoin";

    public const string OP_CREATE= "create";
    public const string OP_FETCH = "fetch";
    public const string OP_UPDATE = "update";
    public const string OP_DELETE = "delete";

    private ?string $countDistinct = null;
    private array $distinct = [];
    private array $excludeRelations = [];
    private Type $type;
    private ?AbstractPlatform $databasePlatform = null;
    private array $groupBy = [];
    private bool|array $hydrateRelations = false;
    private array $join = [];
    private ?int $limit = null;
    private int $offset = 0;
    private array $orderBy = [];
    private array $properties = [];
    private string $schema;
    private bool $toArray = false;
    private array $validationGroup = [];
    private array $values = [];
    private array $where = [];

    public function __construct(string $type)
    {
        $this->type = TypeManager::get($type);
    }

    public function countDistinct(string $property): static
    {
        if (! $this->type->hasProperty($property)) {
            return $this;
        }

        $column = $this->type->getProperty($property)->getName();
        $this->countDistinct = "COUNT(DISTINCT $column) AS count";
        return $this;
    }

    public function create(?Type $type = null): Type
    {
        // hydrate and validate the type
        if (null === $type) {
            $hydrator = new TypeInputHydrator();
            $type = $hydrator->hydrate($this->type, $this->values);
        }

        if (! $type->isValid(self::OP_CREATE)) {
            throw new Exception\InvalidInputException($type->getValidationMessages());
        }

        // execute the query
        try {
            $query = $this->prepareQuery(self::OP_CREATE);
            $result = $query->executeStatement();
        } catch (\Throwable $e) {
            throw new Exception\DatabaseQueryException($e);
        }

        // check the result
        if (! \is_numeric($result) || \intval($result) == 0) {
            LoggerManager::get()->error("Content not created", [
                'sql' => $query->getSQL(),
                'parameters' => $query->getParameters(),
            ]);
        }
        
        return $this->type;
    }

    public function delete(): bool
    {
        $query = $this->prepareQuery(self::OP_DELETE);
        \var_dump($query->getSQL());
        \xdebug_var_dump($query->getParameters());
        return false;
    }

    public function distinct(string $property): static
    {
        $this->distinct[] = $property;
        return $this;
    }

    public function excludeRelations(string|array $relations): static {
        if (\is_string($relations)) {
            $this->excludeRelations[] = $relations;
            return $this;
        }

        foreach ($relations as $relation) {
            $this->excludeRelations[] = $relation;
        }

        return $this;
    }

    public function fetch(): Type
    {
        $query = $this->prepareQuery(self::OP_FETCH);
        try {
            $result = $query->setMaxResults(1)
                ->executeQuery()
                ->fetchAssociative();
        } catch (\Throwable $e) {
            LoggerManager::get()->error($e->getMessage(), [
                'sql' => $query->getSQL(),
                'params' => $query->getParameters(),
            ]);
            return $this->type;
        }

        if (! empty($result)) {
            $this->type->hydrate($result, $this->databasePlatform);
        }

        return $this->type;
    }

    public function fetchAll(): Collection
    {
        $query = $this->prepareQuery(self::OP_FETCH);
        try {
            $iterable = $query->setFirstResult($this->offset)
                ->setMaxResults($this->limit)
                ->executeQuery()
                ->iterateAssociative();
        } catch (\Throwable $e) {
            LoggerManager::get()->error($e->getMessage(), [
                'sql' => $query->getSQL(),
                'params' => $query->getParameters(),
            ]);
            return Collection::empty();
        }

        $contentType = $this->type;
        $platform = $this->databasePlatform;
        $toArray = $this->toArray;

        return Collection::fromCallable(static function () use ($iterable, $toArray, $contentType, $platform): \Generator {
            foreach ($iterable as $row) {
                yield $toArray
                    ? $contentType->hydrate($row, $platform)->toArray()
                    : $contentType->hydrate($row, $platform);
            }
        });
    }

    public function fetchAllLazy(): Collection
    {
        $query = $this->prepareQuery(self::OP_FETCH);
        try {
            $iterable = $query->setFirstResult($this->offset)
                ->setMaxResults($this->limit)
                ->executeQuery()
                ->iterateAssociative();
        } catch (\Throwable $e) {
            LoggerManager::get()->error($e->getMessage(), [
                'sql' => $query->getSQL(),
                'params' => $query->getParameters(),
            ]);
            return Collection::empty();
        }

        $contentType = $this->type;
        $platform = $this->databasePlatform;
        $toArray = $this->toArray;

        return Collection::fromCallable(static function () use ($iterable, $toArray, $contentType, $platform): \Generator {
            foreach ($iterable as $row) {
                yield $toArray
                    ? $contentType->hydrate($row, $platform)->toArray()
                    : $contentType->hydrate($row, $platform);
            }
        });
    }

    public function getQuery(string $operation): QueryBuilder
    {
        return $this->prepareQuery($operation);
    }

    public function groupBy(string $groupBy): static
    {
        $this->groupBy[] = $groupBy;
        return $this;
    }

    public function relations(bool|array $relations = true): static
    {
        $this->hydrateRelations = $relations;        
        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offset = $offset;
        return $this;
    }

    public function orderBy(string $propertyOrSchema, string $direction = "asc", ?string $relationProperty = null): static
    {
        // normalize the column name
        if ($this->type->hasProperty($propertyOrSchema)) {
            $property = $this->type->getProperty($propertyOrSchema);
            if (null === $relationProperty) {
                $column = "{$this->type->getTable()}.{$property->getName()}";
                $this->orderBy[$column] = $direction;
                return $this;
            }

            if (! $property->hasRelation()) {
                LoggerManager::get()->warning("Invalid order by property");
                return $this;
            }

            $relationType = $property->getRelation()->getRelationType();
            if (! $relationType->hasProperty($relationProperty)) {
                LoggerManager::get()->warning("Invalid order by property");
                return $this;
            }

            $relationAlias = $property->getRelation()->getAlias();
            $relationProperty = $relationType->getProperty($relationProperty);
            $column = "{$relationAlias}.{$relationProperty->getName()}";
            $this->orderBy[$column] = $direction;
            return $this;

        } else {
            // try a schema
            foreach ($this->type->getProperties() as $property) {
                if (! $property->hasRelation()) {
                    continue;
                }

                if ($property->getRelation()->getRelationIdentifier() !== $propertyOrSchema) {
                    continue;
                }

                $relation = $property->getRelation()->getRelationType();
                $relationAlias = $property->getRelation()->getAlias();
                if (null !== $relationProperty) {
                    if (! $relation->hasProperty($relationProperty)) {
                        LoggerManager::get()->warning("Invalid order by relation property");
                        return $this;
                    }
                    $propertyName = $relation->getProperty($relationProperty)->getName();
                    $column = "{$relationAlias}.{$propertyName}";
                    $this->orderBy[$column] = $direction;
                    return $this;
                }

                $column = "{$relationAlias}.{$relation->getAutoIncrement()->getName()}";
                $this->orderBy[$column] = $direction;
                return $this;
            }
        }

        return $this;
    }

    public function properties(string $schema, array $properties): static
    {
        $this->properties[$schema] = $properties;
        return $this;
    }

    public function set(string $identifier, mixed $value, bool $byName = false): static
    {
        if (true === $byName) {
            $this->values[$identifier] = $value;
            return $this;
        }
        
        if (! $this->type->hasProperty($identifier)) {
            throw new \InvalidArgumentException("Property $identifier does not exist");
        }

        $property = $this->type->getProperty($identifier);
        $this->values[$property->getName()] = $value;
        return $this;
    }

    public function toArray(): static
    {
        $this->toArray = true;
        return $this;
    }

    public function update(?Type $type = null): Type
    {
        if (empty($this->values)) {
            throw new \InvalidArgumentException("Nothing to update");
        }

        // get a hydrated type
        $hydrator = new TypeInputHydrator();
        $hydrated = null === $type
            ? $hydrator->hydrate($this->type, $this->values)
            : $hydrator->hydrate($type, $this->values);

        // validate properties being updated
        $hydrated->setValidationGroup($this->values);
        if (! $hydrated->isValid(self::OP_UPDATE)) {
            throw new Exception\InvalidInputException($hydrated->getValidationMessages());
        }

        $query = $this->prepareQuery(self::OP_UPDATE);
        if (null !== $type) {
            $query->andWhere($query->expr()->eq(
                $type->getAutoIncrement()->getName(),
                $query->createNamedParameter(
                    $type->getAutoIncrement()->getValue()
                )
            ));
        }
        
        $result = $query->executeStatement();
        if (! \is_numeric($result) || \intval($result) == 0) {
            LoggerManager::get()->info("No updates by: ", [
                'sql' => $query->getSQL(),
                'parameters' => $query->getParameters(),
            ]);
        }

        return $hydrated;
    }

    public function values(array $values, bool $byName = false): static
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $byName);
        }

        return $this;
    }

    public function where(
        string $identifier,
        mixed $value,
        null|string|array $relations = null,
        string $expression = self::MOD_EQ
    ): static {
        $this->where[] = new Where($this->type, $identifier, $value, $relations, $expression);
        return $this;
    }

    public function whereOr(Query ...$queryExpressions): static
    {
        foreach ($queryExpressions as $query) {
            // @todo
        }
        return $this;
    }

    public static function schema(string $schema): self
    {
        return new self($schema);
    }

    protected function applyWhereExpression(
        QueryBuilder $queryBuilder,
        Property $property,
        string $column,
        string $expression,
        mixed $value
    ): void {
        switch ($expression) {
            case self::MOD_EQ:
                $queryBuilder->andWhere($queryBuilder->expr()->eq(
                    $column,
                    $queryBuilder->createNamedParameter(
                        $value,
                        $property->getDatabaseType()->getBindingType()
                    )
                ));
                break;

            case self::MOD_GT:
                $queryBuilder->andWhere($queryBuilder->expr()->gt(
                    $column,
                    $queryBuilder->createNamedParameter(
                        $value,
                        $property->getDatabaseType()->getBindingType()
                    )
                ));
                break;

            case self::MOD_GTE:
                $queryBuilder->andWhere($queryBuilder->expr()->gte(
                    $column,
                    $queryBuilder->createNamedParameter(
                        $value,
                        $property->getDatabaseType()->getBindingType()
                    )
                ));
                break;

            case self::MOD_IN:
                $queryBuilder->andWhere($queryBuilder->expr()->in(
                    $column,
                    \array_map(static fn (string $property): string => "'$property'", $value))
                );
                break;

            case self::MOD_ISNULL:
                if (FALSE === $value) {
                    $queryBuilder->andWhere($queryBuilder->expr()->isNotNull($column));
                } elseif (TRUE === $value) {
                    $queryBuilder->andWhere($queryBuilder->expr()->isNull($column));
                }
                break;

            case self::MOD_LT:
                $queryBuilder->andWhere($queryBuilder->expr()->lt(
                    $column,
                    $queryBuilder->createNamedParameter(
                        $value,
                        $property->getDatabaseType()->getBindingType()
                    )
                ));
                break;

            case self::MOD_LTE:
                $queryBuilder->andWhere($queryBuilder->expr()->lte(
                    $column,
                    $queryBuilder->createNamedParameter(
                        $value,
                        $property->getDatabaseType()->getBindingType()
                    )
                ));
                break;

            case self::MOD_NOTIN:
                $queryBuilder->andWhere($queryBuilder->expr()->notIn(
                    $column,
                    \array_map(static fn (string $property): string => "'$property'", $value))
                );
                break;
        }
    }

    protected function prepareQuery(string $operation): QueryBuilder
    {
        $connection = DatabaseManager::getConnection($this->type->getDatabase());
        $this->databasePlatform = $connection->getDatabasePlatform();
        $queryBuilder = $connection->createQueryBuilder();

        switch ($operation) {
            case self::OP_CREATE:
                $queryBuilder->insert($this->type->getTable());
                foreach ($this->type->getProperties() as $property) {
                    if ($property->isAutoIncrement()) {
                        continue;
                    }

                    if (true === $property->getNotNull() && null === $property->getValue()) {
                        if (\is_callable($property->getDefaultValue())) {
                            $property->setValue(\call_user_func($property->getDefaultValue()));
                        } else {
                            $property->setValue($property->getDefaultValue());
                        }
                    }

                    $value = $property->getValue();
                    if ($value instanceof Type) {
                        $value = $value->getAutoIncrement()->getValue();
                    }

                    $queryBuilder->setValue(
                        $property->getName(),
                        $queryBuilder->createNamedParameter(
                            $property->getDatabaseType()->convertToDatabaseValue($value, $this->databasePlatform),
                            $property->getDatabaseType()->getBindingType()
                        )
                    );
                }
                break;

            case self::OP_DELETE:
                $queryBuilder->delete($this->type->getTable());
                $this->applyWhereExpressions($queryBuilder);
                break;

            case self::OP_FETCH:
                $this->applyRelations($this->type, $queryBuilder);
                $this->applyWhereExpressions($queryBuilder);
                $queryBuilder->from($this->type->getTable(), $this->type->getTable());
                foreach ($this->groupBy as $expression) {
                    $queryBuilder->addGroupBy($expression);
                }

                foreach ($this->orderBy as $property => $direction) {
                    $queryBuilder->addOrderBy($property, $direction);
                }
                break;
            
            case self::OP_UPDATE:
                $queryBuilder->update($this->type->getTable());
                $this->applyWhereExpressions($queryBuilder);
                foreach ($this->values as $name => $value) {
                    if (! $this->type->hasProperty($name)) {
                        continue;
                    }

                    $property = $this->type->getProperty($name);
                    $queryBuilder->set(
                        $property->getName(),
                        $queryBuilder->createNamedParameter(
                            $property->getDatabaseType()->convertToDatabaseValue($value, $this->databasePlatform),
                            $property->getDatabaseType()->getBindingType()
                        )
                    );
                }
                break;
        }

        return $queryBuilder;
    }

    private function applyRelations(Type $content, QueryBuilder $queryBuilder): void
    {
        if (null !== $this->countDistinct) {
            $queryBuilder->select($this->countDistinct);
            return;
        }

        if (FALSE === $this->hydrateRelations) {
            // local properties only
            $this->hydrateLocalProperties($queryBuilder, $content);
        } elseif (TRUE === $this->hydrateRelations) {
            // all properties
            $this->hydrateLocalProperties($queryBuilder, $content);
            $this->hydrateRemoteProperties($queryBuilder, $content);
        } else {
            // something in between
        }
    }

    private function applyWhereExpressions(QueryBuilder $queryBuilder): void
    {
        foreach ($this->where as $where) {
            \assert($where instanceof Where);

            // raw queries
            if ($where->isRaw()) {
                $value = $where->getValue();
                $queryBuilder->andWhere($where->getColumn());
                if (\is_array($value)) {
                    foreach ($value as $k => $v) {
                        $queryBuilder->setParameter($k, $v);
                    }
                }
                continue;
            }

            $this->applyWhereExpression(
                $queryBuilder,
                $where->getProperty(),
                $where->getColumn(),
                $where->getExpression(),
                $where->getValue()
            );
        }
    }

    private function hydrateLocalProperties(QueryBuilder $queryBuilder, Type $contentType, ?string $alias = null): void
    {
        if (\in_array($contentType->getIdentifier(), $this->excludeRelations)) {
            return;
        }

        foreach ($contentType->getProperties() as $property) {
            if ($property->hasRelation()) {
                continue;
            }

            $table = $alias ?? $contentType->getTable();
            $queryBuilder->addSelect(\sprintf(
                "%s AS %s",
                "$table.{$property->getName()}",
                "{$table}__{$property->getName()}"
            ));
        }
    }

    private function hydrateRemoteProperties(
        QueryBuilder $queryBuilder,
        Type $type,
        array &$duplicates = [],
        array &$relations = []
    ): void {
        foreach ($type->getProperties() as $property) {
            if (! $property->hasRelation()) {
                continue;
            }

            if (\in_array($property->getRelation()->getRelationIdentifier(), $this->excludeRelations, true)) {
                continue;
            }

            if (! \in_array($property->getIdentifier(), $relations, true)) {
                $this->joinRelation($queryBuilder, $property, $type->getTable());
                $relations[] = $property->getIdentifier();
            }

            $this->hydrateLocalProperties($queryBuilder, $property->getRelation()->getRelationType(), $property->getRelation()->getAlias());

            foreach($property->getRelation()->getRelationProperties() as $innerProperty) {
                if (\in_array($property->getRelation()->getAlias() . '__' . $innerProperty->getName(), $duplicates, true)) {
                    continue;
                }
                
                $duplicates[] = $property->getRelation()->getAlias() . '__' . $innerProperty->getName();

                if ($innerProperty->hasRelation()) {
                    $innerContent = $innerProperty->getRelation()->getRelationType();
                    $innerRelationAlias = $innerProperty->getRelation()->getAlias();
                    $duplicates[] = $innerRelationAlias . '__' . $innerProperty->getName();

                    if  (! \in_array($innerProperty->getIdentifier(), $relations, true)) {
                        $this->joinRelation($queryBuilder, $innerProperty, $property->getRelation()->getAlias());
                        $relations[] = $innerProperty->getIdentifier();
                    }

                    $this->hydrateLocalProperties($queryBuilder, $innerContent, $innerRelationAlias);
                    $this->hydrateRemoteProperties($queryBuilder, $innerContent, $duplicates, $relations);
                }
            }
        }
    }

    private function joinRelation(QueryBuilder $queryBuilder, Property $property, string $table): void
    {
        $queryBuilder->leftJoin(
            fromAlias: $this->type->getTable(),
            join: $property->getRelation()->getTable(),
            alias: $property->getRelation()->getAlias(),
            condition: $table . '.' . $property->getName() . '=' . $property->getName() . '.' . $property->getRelationProperty()->getName()
        );
    }
}
