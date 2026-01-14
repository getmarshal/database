<?php

declare(strict_types=1);

namespace Marshal\Database\Query;

use loophp\collection\Collection;
use Marshal\Database\Hydrator\DatabaseResultHydrator;
use Marshal\Database\Query;
use Marshal\Database\Query\Trait\WhereTrait;
use Marshal\Database\QueryBuilder;
use Marshal\Database\Schema\Property;
use Marshal\Database\Schema\Type;
use Marshal\Database\Schema\TypeManager;
use Marshal\Database\Schema\TypeRelation;
use Marshal\Utils\Logger\LoggerManager;

final class Select extends Query
{
    use WhereTrait;

    private array $excludeRelations = [];
    private array $groupBy = [];
    private ?int $limit = null;
    private int $offset = 0;
    private array $orderBy = [];
    private array $properties = [];
    private bool $toArray = false;

    public function addProperty(string $identifier): static
    {
        $this->properties[] = $identifier;
        return $this;
    }

    public function alias(string $schema, string $column, string $alias): static
    {
        return $this;
    }

    public function distinct(string $property): static
    {
        $this->properties[] = "DISTINCT $property AS $property";
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

    public function fetch(): array
    {
        return $this->fetchArrayResult($this->prepare());
    }

    public function fetchAll(): array
    {
        $query = $this->prepare();
        try {
            $result = $query->setFirstResult($this->offset)
                ->setMaxResults($this->limit)
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            LoggerManager::get()->error($e->getMessage(), [
                'sql' => $query->getSQL(),
                'params' => $query->getParameters(),
            ]);
            return [];
        }

        return $result;
    }

    public function fetchAllLazy(): Collection
    {
        $query = $this->prepare();
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

        $type = $this->type;
        $hydrator = new DatabaseResultHydrator;
        $platform = $query->getDatabasePlatform();
        $toArray = $this->toArray;

        return Collection::fromCallable(static function () use ($iterable, $toArray, $type, $platform, $hydrator): \Generator {
            foreach ($iterable as $row) {
                $hydrator->hydrate($type, $row, $platform);
                yield $toArray ? $type->toArray() : $type;
            }
        });
    }

    public function fetchType(): Type
    {
        $query = $this->prepare();
        $result = $this->fetchArrayResult($query);

        if (! empty($result)) {
            $hydrator = new DatabaseResultHydrator();
            $hydrator->hydrate($this->type, $result, $query->getDatabasePlatform());
        }

        return $this->type;
    }

    public function from(string $identifier): static
    {
        $this->type = TypeManager::get($identifier);
        return $this;
    }

    public function groupBy(string $groupBy): static
    {
        $this->groupBy[] = $groupBy;
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

    public function orderBy(string|array $identifier, string $direction = "ASC"): static
    {
        if (\is_array($identifier)) {
            $identifier = \implode('__', $identifier);
        }

        $this->orderBy[$identifier] = $direction;
        return $this;
    }

    public function properties(array $properties): static
    {
        foreach ($properties as $property) {
            $this->properties[] = $property;
        }

        return $this;
    }

    public function toArray(): static
    {
        $this->toArray = true;
        return $this;
    }

    protected function prepare(): QueryBuilder
    {
        if (! isset($this->type)) {
            throw new \InvalidArgumentException(\sprintf(
                "Query has no from clause"
            ));
        }

        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->from($this->type->getTable(), $this->type->getTable());

        $this->applySelects($queryBuilder);
        $this->applyRelations($this->type, $queryBuilder);
        $this->applyWhereExpressions($queryBuilder);
        $this->applyOrderByExpressions($queryBuilder);

        foreach ($this->groupBy as $expression) {
            $queryBuilder->addGroupBy($expression);
        }

        return $queryBuilder;
    }

    private function applyOrderByExpressions(QueryBuilder $queryBuilder): void
    {
        foreach ($this->orderBy as $identifier => $direction) {
            if (FALSE === \strpos($identifier, '__')) {
                if (! $this->type->hasProperty($identifier)) {
                    LoggerManager::get()->warning(\sprintf(
                        "Invalid order by expression %s: Type %s has no property %s",
                        $identifier,
                        $this->type->getIdentifier(),
                        $identifier
                    ));
                    continue;
                }

                $property = $this->type->getProperty($identifier);
                $column = "{$this->type->getTable()}.{$property->getName()}";
                $queryBuilder->addOrderBy($column, $direction);
                continue;
            }

            // allow one level for now
            $parts = \explode('__', $identifier);
            if (\count($parts) > 2) {
                LoggerManager::get()->warning(\sprintf(
                    "Invalid order by expression %s: Too many relation levels",
                    $identifier
                ));
                continue;
            }

            // first item must be local relation
            if (! $this->type->isRelationProperty($parts[0])) {
                LoggerManager::get()->warning(\sprintf(
                    "Invalid order by expression %s: Type %s has no relation property %s",
                    $identifier,
                    $this->type->getIdentifier(),
                    $parts[0]
                ));
                continue;
            }

            // relation must have last item as property
            $relation = $this->type->getRelation($parts[0]);
            if (! $relation->getRelationType()->hasProperty($parts[1])) {
                LoggerManager::get()->warning(\sprintf(
                    "Invalid order by expression %s: Relation %s has no property %s",
                    $identifier,
                    $relation->getIdentifier(),
                    $parts[1]
                ));
                continue;
            }

            $property = $relation->getRelationType()->getProperty($parts[1]);
            $column = "{$relation->getAlias()}.{$property->getName()}";
            $queryBuilder->addOrderBy($column, $direction);
        }
    }

    private function applySelects(QueryBuilder $queryBuilder): void
    {
        $properties = $this->properties;
        if (empty($properties) || \count($properties) === 1 && $properties[0] === '*') {
            $properties = \array_map(
                static fn (Property $property): string => $property->getName(),
                $this->type->getProperties()
            );
        }

        // local selects
        foreach ($properties as $identifier) {
            if ($this->type->hasProperty($identifier)) {
                $property = $this->type->getProperty($identifier);
                $column = "{$this->type->getTable()}.{$property->getName()}";
                $alias = "{$this->type->getTable()}__{$property->getName()}";
                $queryBuilder->addSelect("$column AS $alias");
                continue;
            }

            // raw identifier
            $queryBuilder->addSelect($identifier);
        }
    }

    private function fetchArrayResult(QueryBuilder $query): array
    {
        try {
            $result = $query->setMaxResults(1)
                ->executeQuery()
                ->fetchAssociative();
        } catch (\Throwable $e) {
            LoggerManager::get()->error($e->getMessage(), [
                'sql' => $query->getSQL(),
                'params' => $query->getParameters(),
            ]);
            return [];
        }

        return \is_array($result) ? $result : [];
    }
}