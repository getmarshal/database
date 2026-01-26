<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Trait;

use Marshal\Database\Query;
use Marshal\Database\QueryBuilder;
use Marshal\Database\Schema\Type;
use Marshal\Utils\Config;
use Marshal\Utils\Logger\LoggerManager;

trait WhereTrait
{
    private array $processedWhereRelations = [];
    private array $where = [];

    public function where(
        array|string $identifier,
        mixed $value,
        string $expression = Query::WHERE_EQ
    ): static {
        $this->where[] = [
            'identifier' => \is_array($identifier) ? \implode('__', $identifier) : $identifier,
            'value' => $value,
            'expression' => $expression,
        ];

        return $this;
    }

    private function applyWhereExpressions(QueryBuilder $queryBuilder, Type $type): void
    {
        $expressions = Config::get('database_expressions')['where'];
        foreach ($this->where as $where) {
            if ($where['expression'] === Query::WHERE_RAW) {
                $this->applyWhereRawExpression($queryBuilder, $where['identifier'], $where['value']);
                continue;
            }

            if (! isset($expressions[$where['expression']])) {
                LoggerManager::get()->warning(\sprintf(
                    "Where expression %s not found in config",
                    $where['expression']
                ));
                continue;
            }

            $expression = new $expressions[$where['expression']];
            if (FALSE !== \strpos($where['identifier'], '__')) {
                [$relation, $property] = $this->applyWhereRelationExpression($this->type, $where['identifier']);                
                $table = $relation->getAlias();
                $column = "{$relation->getAlias()}.{$property->getName()}";
            } else {
                if (! $type->hasProperty($where['identifier'])) {
                    LoggerManager::get()->warning(\sprintf(
                        "Invalid where query identifier: Type %s has no property %s",
                        $this->type->getIdentifier(),
                        $where['identifier']
                    ));
                    continue;
                }

                $table = $type->getTable();
                $property = $type->getProperty($where['identifier']);
                $column = "{$table}.{$property->getName()}";
            }

            try {
                $property->setValue($where['value']);
                $expression($queryBuilder, $property, $column);
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
                $value = $v instanceof Type ? $v->getAutoIncrement()->getValue() : $v;
                $queryBuilder->setParameter($k, $value);
            }
        }
    }

    private function applyWhereRelationExpression(Type $type, string $identifier): array
    {
        $parts = explode('__', $identifier);
        $propertyIdentifier = \array_pop($parts);
        $relationIdentifier = \array_pop($parts);

        // basic 2 parts
        if (empty($parts)) {
            if (! $type->isRelationProperty($relationIdentifier)) {
                throw new \InvalidArgumentException(\sprintf(
                    "Invalid where identifier %s. %s is not a relation property of %s",
                    $identifier, $relationIdentifier, $type->getIdentifier()
                ));
            }

            $relation = $type->getRelation($relationIdentifier);
            $property = $relation->getRelationType()->getProperty($propertyIdentifier);
        } else {
            if (! $type->isRelationProperty($parts[0])) {
                throw new \InvalidArgumentException(\sprintf(
                    "Invalid where identifier %s. %s is not a relation property of %s",
                    $identifier, $parts[0], $type->getIdentifier()
                ));
            }

            while (\count($parts) > 0) {
                $nextRelationIdentifier = \array_shift($parts);
                if (\count($parts) === 0) {
                    $useType = isset($nextRelation) ? $nextRelation->getRelationType() : $type;
                    if (! $useType->isRelationProperty($nextRelationIdentifier)) {
                        throw new \InvalidArgumentException(\sprintf(
                            "Invalid where identifier %s. %s is not a relation property of %s",
                            $identifier, $nextRelationIdentifier, $type->getIdentifier()
                        ));
                    }

                    $nextRelation = $useType->getRelation($nextRelationIdentifier);
                    $relation = $nextRelation->getRelationType()->getRelation($relationIdentifier);
                    $property = $nextRelation->getRelationType()->getProperty($propertyIdentifier);
                } else {
                    $nextRelation = $type->getRelation($nextRelationIdentifier);

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
