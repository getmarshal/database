<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Modifier;

use Marshal\Database\QueryBuilder;
use Marshal\Utils\Logger\LoggerManager;
use Marshal\Database\Schema\ContentRelation;
use Marshal\Database\Schema\Content;

trait Relations
{
    private array $excludeRelations = [];
    private array $processedRelations = [];

    private function applyRelations(QueryBuilder $queryBuilder, Content $content): void
    {
        // @todo use query properties to apply relations selectively
        foreach ($content->getRelations() as $relation) {
            \assert($relation instanceof ContentRelation);

            // @todo review and test these exclusions
            if ($this->isExcludedRelation($relation, $content)) {
                continue;
            }

            // apply relation joins
            $this->applyRelationJoin($queryBuilder, $relation, $content);

            // repeat the process for nested relations
            $this->processedRelations[] = $relation->getAlias();
            $this->applyRelations($queryBuilder, $relation->getRelationType());
        }
    }

    private function applyRelationJoin(QueryBuilder $queryBuilder, ContentRelation $relation, Content $content): void
    {
        switch ($relation->getJoinType()) {
            case ContentRelation::JOIN_INNER:
                $queryBuilder->innerJoin(
                    fromAlias: $this->content->getTable(),
                    join: $relation->getRelationType()->getTable(),
                    alias: $relation->getAlias(),
                    condition: $relation->getRelationCondition($content)
                );
                break;

            case ContentRelation::JOIN_RIGHT:
                $queryBuilder->rightJoin(
                    fromAlias: $this->content->getTable(),
                    join: $relation->getRelationType()->getTable(),
                    alias: $relation->getAlias(),
                    condition: $relation->getRelationCondition($content)
                );
                break;

            case ContentRelation::JOIN_LEFT:
                $queryBuilder->leftJoin(
                    fromAlias: $this->content->getTable(),
                    join: $relation->getRelationType()->getTable(),
                    alias: $relation->getAlias(),
                    condition: $relation->getRelationCondition($content)
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

    private function isExcludedRelation(ContentRelation $relation, Content $content): bool
    {
        $localProperty = $content->getProperty($relation->getLocalProperty());
        return \in_array($relation->getIdentifier(), $this->excludeRelations, true) ||
            \in_array($relation->getRelationType()->getTable(), $this->excludeRelations, true) ||
            \in_array($relation->getAlias(), $this->excludeRelations, true) ||
            \in_array($localProperty->getIdentifier(), $this->excludeRelations, true) ||
            \in_array($relation->getAlias(), $this->processedRelations, true) ||
            \in_array('*', $this->excludeRelations, true);
    }
}
