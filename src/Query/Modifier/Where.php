<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Modifier;

use Marshal\Database\QueryBuilder;
use Marshal\Utils\Config;
use Marshal\Utils\Logger\LoggerManager;
use Marshal\Database\Schema\Content;

trait Where
{
    use WhereConstants;

    private array $processedWhereRelations = [];
    private array $where = [];

    public function where(
        array|string $identifier,
        mixed $value,
        string $expression = QueryBuilder::WHERE_EQ
    ): static {
        $this->where[] = [
            'identifier' => \is_array($identifier) ? \implode('__', $identifier) : $identifier,
            'value' => $value,
            'operator' => $expression,
        ];

        return $this;
    }

    private function applyWhereExpressions(QueryBuilder $queryBuilder, Content $content): void
    {
        $expressions = Config::get('database_expressions')['where'];
        foreach ($this->where as $where) {
            if ($where['operator'] === QueryBuilder::WHERE_RAW) {
                $this->applyWhereRawExpression($queryBuilder, $where['identifier'], $where['value']);
                continue;
            }

            if (! isset($expressions[$where['operator']])) {
                LoggerManager::get()->warning(\sprintf(
                    "Where expression %s not found in config",
                    $where['expression']
                ));
                continue;
            }

            if (FALSE !== \strpos($where['identifier'], '__')) {
                [$relation, $property] = $this->applyWhereRelationExpression($content, $where['identifier']);
                $table = $relation->getAlias();
                $column = "{$relation->getAlias()}.{$property->getName()}";
            } else {
                if (! $content->hasProperty($where['identifier'])) {
                    LoggerManager::get()->warning(\sprintf(
                        "Invalid where query identifier: Content %s has no property %s",
                        $content->getSchemaIdentifier(),
                        $where['identifier']
                    ));
                    continue;
                }

                $table = $content->getTable();
                $property = $content->getProperty($where['identifier']);
                $column = "{$table}.{$property->getName()}";
            }

            try {
                $operator = new $expressions[$where['operator']];
                $property->setValue($where['value']);
                $operator($queryBuilder, $property, $column, $where['value']);
            } catch (\Throwable $e) {
                LoggerManager::get()->error($e->getMessage(), $where);
            }
        }
    }

    private function applyWhereRawExpression(QueryBuilder $queryBuilder, string $identifier, mixed $value): void
    {
        $queryBuilder->andWhere($identifier);
        if (\is_array($value)) {
            foreach ($value as $k => $v) {
                $value = $v instanceof Content ? $v->getAutoIncrement()->getValue() : $v;
                $queryBuilder->setParameter($k, $value);
            }
        }
    }

    private function applyWhereRelationExpression(Content $content, string $identifier): array
    {
        $parts = explode('__', $identifier);
        $propertyIdentifier = \array_pop($parts);
        $relationIdentifier = \array_pop($parts);

        // basic 2 parts
        if (empty($parts)) {
            if (! $content->isRelationProperty($relationIdentifier)) {
                throw new \InvalidArgumentException(\sprintf(
                    "Invalid where identifier %s. %s is not a relation property of %s",
                    $identifier, $relationIdentifier, $content->getSchemaIdentifier()
                ));
            }

            $relation = $content->getRelation($relationIdentifier);
            $property = $relation->getRelationType()->getProperty($propertyIdentifier);
        } else {
            if (! $content->isRelationProperty($parts[0])) {
                throw new \InvalidArgumentException(\sprintf(
                    "Invalid where identifier %s. %s is not a relation property of %s",
                    $identifier, $parts[0], $content->getSchemaIdentifier()
                ));
            }

            while (\count($parts) > 0) {
                $nextRelationIdentifier = \array_shift($parts);
                if (\count($parts) === 0) {
                    $useType = isset($nextRelation) ? $nextRelation->getRelationType() : $content;
                    if (! $useType->isRelationProperty($nextRelationIdentifier)) {
                        throw new \InvalidArgumentException(\sprintf(
                            "Invalid where identifier %s. %s is not a relation property of %s",
                            $identifier, $nextRelationIdentifier, $content->getSchemaIdentifier()
                        ));
                    }

                    $nextRelation = $useType->getRelation($nextRelationIdentifier);
                    $relation = $nextRelation->getRelationType()->getRelation($relationIdentifier);
                    $property = $nextRelation->getRelationType()->getProperty($propertyIdentifier);
                } else {
                    $nextRelation = $content->getRelation($nextRelationIdentifier);

                    // @todo handle his block for > 3 relations
                }
            }
        }

        if (! isset($relation) || ! isset($property)) {
            throw new \RuntimeException(\sprintf(
                "Invalid where identifier %s. Relation not found",
                $identifier
            ));
        }

        return [$relation, $property];
    }
}
