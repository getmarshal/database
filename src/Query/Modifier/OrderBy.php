<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Modifier;

use Marshal\Database\QueryBuilder;
use Marshal\Database\Schema\Content;
use Marshal\Utils\Logger\LoggerManager;

trait OrderBy
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
                if (! $this->content->hasProperty($identifier)) {
                    LoggerManager::get()->warning(\sprintf(
                        "Invalid order by expression %s: Type %s has no property %s",
                        $identifier,
                        $this->content->getSchemaIdentifier(),
                        $identifier
                    ));
                    continue;
                }

                if ($this->content->isRelationProperty($identifier)) {
                    $relation = $this->content->getRelation($identifier);
                    $table = $relation->getAlias();
                    $name = $relation->getRelationProperty()->getName();
                } else {
                    $table = $this->content->getTable();
                    $name = $this->content->getProperty($identifier)->getName();
                }

                $column = "{$table}.{$name}";
                $duplicates[] = $column;
                $queryBuilder->addOrderBy($column, $direction);
                continue;
            }

            $this->orderRelation($this->content, $queryBuilder, $identifier, $direction, $duplicates);
        }
    }

    private function orderRelation(Content $content, QueryBuilder $queryBuilder, string $identifier, string $direction, &$duplicates = []): void
    {
        $parts = \explode('__', $identifier);
        foreach ($parts as $index => $part) {
            foreach ($content->getRelations() as $relation) {
                $localProperty = $content->getProperty($relation->getLocalProperty());
                if (
                    $localProperty->getIdentifier() !== $part &&
                    $localProperty->getName() !== $part
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
