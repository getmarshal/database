<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Trait;

use Marshal\Database\QueryBuilder;
use Marshal\Database\Schema\Type;
use Marshal\Utils\Logger\LoggerManager;

trait OrderByTrait
{
    private array $orderBy = [];

    public function orderBy(string|array $identifier, string $direction = "ASC"): static
    {
        if (\is_array($identifier)) {
            $identifier = \implode('__', $identifier);
        }

        $this->orderBy[$identifier] = $direction;
        return $this;
    }

    private function applyOrderByExpressions(QueryBuilder $queryBuilder): void
    {
        $duplicates = [];
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

                if ($this->type->isRelationProperty($identifier)) {
                    $relation = $this->type->getRelation($identifier);
                    $table = $relation->getAlias();
                    $name = $relation->getRelationProperty()->getName();
                } else {
                    $table = $this->type->getTable();
                    $name = $this->type->getProperty($identifier)->getName();
                }

                $column = "{$table}.{$name}";
                $duplicates[] = $column;
                $queryBuilder->addOrderBy($column, $direction);
                continue;
            }

            $this->orderRelation($this->type, $queryBuilder, $identifier, $direction, $duplicates);
        }
    }

    private function orderRelation(Type $type, QueryBuilder $queryBuilder, string $identifier, string $direction, &$duplicates = []): void
    {
        $parts = \explode('__', $identifier);
        foreach ($parts as $index => $part) {
            foreach ($type->getRelations() as $relation) {
                if (
                    $relation->getLocalProperty()->getIdentifier() !== $part &&
                    $relation->getLocalProperty()->getName() !== $part
                ) {
                    $this->orderRelation($relation->getRelationType(), $queryBuilder, $identifier, $direction, $duplicates);
                } else {
                    if (! isset($parts[$index + 1])) {
                        continue;
                    }

                    if (! $relation->getRelationType()->hasProperty($parts[$index + 1])) {
                        continue;
                    }

                    $property = $relation->getRelationType()->getProperty($parts[$index + 1]);
                    $column = "{$relation->getAlias()}.{$property->getName()}";

                    if (\in_array($column, $duplicates, true)) {
                        continue;
                    }

                    $duplicates[] = $column;
                    $queryBuilder->addOrderBy($column, $direction);
                }
            }
        }
    }
}
