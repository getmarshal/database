<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Trait;

use Marshal\Database\QueryBuilder;
use Marshal\Database\Schema\TypeRelation;
use Marshal\Utils\Logger\LoggerManager;

trait RelationsTrait
{
    private array $excludeRelations = [];
    private array $processedRelations = [];

    private function applyRelations(QueryBuilder $queryBuilder, array $relations): void
    {
        foreach ($relations as $relation) {
            \assert($relation instanceof TypeRelation);

            // @todo review and test these exclusions
            if ($this->isExcludedRelation($relation)) {
                continue;
            }

            // apply relation joins
            $this->applyRelationJoin($queryBuilder, $relation);

            // repeat the process for nested relations
            $this->processedRelations[] = $relation->getAlias();
            $this->applyRelations($queryBuilder, $relation->getRelationType()->getRelations());
        }
    }

    private function applyRelationJoin(QueryBuilder $queryBuilder, TypeRelation $relation): void
    {
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
    }

    private function isExcludedRelation(TypeRelation $relation): bool
    {
        return \in_array($relation->getIdentifier(), $this->excludeRelations, true) ||
            \in_array($relation->getRelationType()->getTable(), $this->excludeRelations, true) ||
            \in_array($relation->getAlias(), $this->excludeRelations, true) ||
            \in_array($relation->getLocalProperty()->getIdentifier(), $this->excludeRelations, true) ||
            \in_array($relation->getAlias(), $this->processedRelations, true) ||
            \in_array('*', $this->excludeRelations, true);
    }
}
