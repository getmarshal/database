<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Trait;

use Doctrine\DBAL\ParameterType;
use Marshal\Database\Query;
use Marshal\Database\QueryBuilder;
use Marshal\Database\Schema\Type;
use Marshal\Database\Schema\TypeRelation;
use Marshal\Utils\Config;
use Marshal\Utils\Logger\LoggerManager;

trait WhereTrait
{
    private array $where = [];

    public function where(
        array|string $identifier,
        mixed $value,
        string $expression = Query::WHERE_EQ
    ): static {
        if (Query::WHERE_RAW === $expression) {
            $this->where[] = [
                'column' => $identifier,
                'expression' => $expression,
                'property' => null,
                'value' => $value instanceof Type
                    ? $value->getAutoIncrement()->getValue()
                    : $value,
            ];
            return $this;
        }

        if (\is_array($identifier)) {
            $identifier = \implode('__', $identifier);
        }

        // check if identifier has relation modifications
        if (FALSE === \strpos($identifier, '__')) {
            if (! $this->type->hasProperty($identifier)) {
                throw new \InvalidArgumentException(\sprintf(
                    "Invalid where query %s: Type %s has no property %s",
                    $identifier,
                    $this->type->getIdentifier(),
                    $identifier
                ));
            }

            $property = $this->type->getProperty($identifier);
            $column = "{$this->type->getTable()}.{$property->getName()}";
            $this->where[] = [
                'column' => $column,
                'expression' => $expression,
                'property' => $property,
                'value' => $value,
            ];
            return $this;
        }

        // first item in identifier has to be a local (relation) property
        $parts = \explode('__', $identifier);
        if (! $this->type->isRelationProperty($parts[0])) {
            throw new \InvalidArgumentException(\sprintf(
                "Invalid where query %s. Property %s is not a relation of %s",
                $identifier,
                $parts[0],
                $this->type->getIdentifier()
            ));
        }

        // keep things simple, for now
        // allow up to 2 levels of relations (4th item is a property)
        if (\count($parts) > 4) {
            throw new \InvalidArgumentException(\sprintf(
                "Too many query where arguments: %s",
                $identifier
            ));
        }

        $relation = $this->type->getRelation($parts[0]);
        if (\count($parts) === 2) {
            // property of top-level relation
            if (! $relation->getRelationType()->hasProperty($parts[1])) {
                throw new \InvalidArgumentException(\sprintf(
                    "Invalid where query %s. Relation %s has no property %s",
                    $identifier,
                    $parts[0],
                    $parts[1]
                ));
            }

            $property = $relation->getRelationType()->getProperty($parts[1]);
            $column = "{$relation->getAlias()}.{$property->getName()}";
        }

        if (\count($parts) === 3) {
            // second arg is relation property of top-level relation
            // thrid arg is propery of second arg
            if (! $relation->getRelationType()->isRelationProperty($parts[1])) {
                throw new \InvalidArgumentException(\sprintf(
                    "Invalid where query %s. Relation %s has no relation property %s",
                    $identifier,
                    $parts[0],
                    $parts[1]
                ));
            }

            $secondRelation = $relation->getRelationType()->getRelation($parts[1]);
            if (! $secondRelation->getRelationType()->hasProperty($parts[2])) {
                throw new \InvalidArgumentException(\sprintf(
                    "Invalid where query %s. Relation %s has no property %s",
                    $identifier,
                    $parts[1],
                    $parts[2]
                ));
            }

            $property = $secondRelation->getRelationType()->getProperty($parts[2]);
            $column = "{$secondRelation->getAlias()}.{$property->getName()}";
        }

        if (\count($parts) === 4) {
            // third arg is relation property of second arg
            // fourth arg is propery of third arg
            if (! $relation->getRelationType()->isRelationProperty($parts[1])) {
                throw new \InvalidArgumentException(\sprintf(
                    "Invalid where query %s. Relation %s has no relation property %s",
                    $identifier,
                    $parts[0],
                    $parts[1]
                ));
            }

            $secondRelation = $relation->getRelationType()->getRelation($parts[1]);
            if (! $secondRelation->getRelationType()->isRelationProperty($parts[2])) {
                throw new \InvalidArgumentException(\sprintf(
                    "Invalid where query %s. Relation %s has no relation property %s",
                    $identifier,
                    $parts[1],
                    $parts[2]
                ));
            }

            $thirdRelation = $secondRelation->getRelationType()->getRelation($parts[2]);
            if (! $thirdRelation->getRelationType()->hasProperty($parts[3])) {
                throw new \InvalidArgumentException(\sprintf(
                    "Invalid where query %s. Relation %s has no property %s",
                    $identifier,
                    $parts[2],
                    $parts[3]
                ));
            }

            $property = $thirdRelation->getRelationType()->getProperty($parts[3]);
            $column = "{$thirdRelation->getAlias()}.{$property->getName()}";
        }

        $this->where[] = [
            'column' => $column,
            'expression' => $expression,
            'property' => $property,
            'value' => $value,
        ];

        return $this;
    }

    private function applyRelations(Type $type, QueryBuilder $queryBuilder, array &$processedRelations = []): void
    {
        foreach ($type->getRelations() as $relation) {
            if (
                \in_array($relation->getIdentifier(), $this->excludeRelations, true)
                || \in_array($relation->getRelationType()->getTable(), $this->excludeRelations, true)
                || \in_array($relation->getAlias(), $processedRelations, true)
                || \in_array('*', $this->excludeRelations, true)
            ) {
                continue;
            }

            // apply relation selects, if specific properties are not requested
            if (empty($this->properties)) {
                foreach ($relation->getRelationType()->getProperties() as $relationProperty) {
                    $column = "{$relation->getAlias()}.{$relationProperty->getName()}";
                    $alias = "{$relation->getAlias()}__{$relationProperty->getName()}";
                    $queryBuilder->addSelect("$column AS $alias");
                }
            }

            // apply relation joins
            switch ($relation->getJoinType()) {
                case TypeRelation::JOIN_INNER:
                    $queryBuilder->innerJoin(
                        fromAlias: $this->type->getTable(),
                        join: $relation->getRelationType()->getTable(),
                        alias: $relation->getAlias(),
                        condition: $relation->getRelationCondition()
                    );
                    break;
                
                case TypeRelation::JOIN_RIGHT:
                    $queryBuilder->rightJoin(
                        fromAlias: $this->type->getTable(),
                        join: $relation->getRelationType()->getTable(),
                        alias: $relation->getAlias(),
                        condition: $relation->getRelationCondition()
                    );
                    break;

                case TypeRelation::JOIN_LEFT:
                    $queryBuilder->leftJoin(
                        fromAlias: $this->type->getTable(),
                        join: $relation->getRelationType()->getTable(),
                        alias: $relation->getAlias(),
                        condition: $relation->getRelationCondition()
                    );
                    break;
                
                default:
                    LoggerManager::get()->warning(\sprintf(
                        "Invalid join type %s on relation %s",
                        $relation->getJoinType(),
                        $relation->getIdentifier()
                    ));
            }

            // repeat the process for nested relations
            $processedRelations[] = $relation->getAlias();
            $this->applyRelations($relation->getRelationType(), $queryBuilder, $processedRelations);
        }
    }

    protected function applyWhereExpressions(QueryBuilder $queryBuilder): void
    {
        $expressions = Config::get('database_expressions');
        foreach ($expressions['where'] ?? [] as $name => $class) {
            $queryBuilder->addExpression($name, $class);
        }

        foreach ($this->where as $where) {
            $expressionClass = $queryBuilder->getExpression($where['expression']);
            \call_user_func_array([$expressionClass, 'applyOperation'], [
                'queryBuilder' => $queryBuilder,
                'column' => $where['column'],
                'value' => $where['value'],
                'parameterType' => isset($where['property'])
                    ? $where['property']->getDatabaseType()->getBindingType()
                    : ParameterType::STRING,
            ]);
        }
    }
}
