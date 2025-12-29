<?php

declare(strict_types=1);

namespace Marshal\Database;

use Doctrine\DBAL\Query\QueryBuilder;

final class Where
{
    private Property $property;
    public function __construct(
        private Type $type,
        private readonly string $identifier,
        private readonly mixed $value,
        private null|string|array $relations = null,
        private readonly string $expression = Query::MOD_EQ,
        private bool $byName = FALSE
    ) {
        if (! \in_array($expression, $this->getAllowedExpressions(), true)) {
            throw new \InvalidArgumentException("Expression type $expression not allowed");
        }

        $this->setProperty();
    }

    public function applyExpression(QueryBuilder $queryBuilder, Property $property, string $column, mixed $value): void
    {
    }

    public function getColumn(): string
    {
        if ($this->isRaw()) {
            return $this->identifier;
        }
        
        if (null !== $this->relations) {
            $type = $this->getProperty()->getRelation()->getRelationType();
            if (\is_string($this->relations)) {
                if (! $type->hasProperty($this->relations)) {
                    throw new \InvalidArgumentException("Type has no identifier");
                }

                // one level of relation
                $property = $type->getProperty($this->relations);
                $alias = $property->hasRelation() ? $property->getRelation()->getAlias() : $type->getTable();
                return "{$alias}.{$property->getName()}";

            } else {
                // an array. multiple relations
                $property = $this->type->getProperty($this->identifier);
                if (\count($this->relations) === 1) {
                    $relationIdentifier = \array_keys($this->relations)[0];
                    $relationColumn = $this->relations[$relationIdentifier];
                    if (! $property->hasRelation()) {
                        throw new \InvalidArgumentException("Invalid relation query");
                    }
                    $relation = $property->getRelation()->getRelationType();
                    if (! $relation->hasProperty($relationColumn)) {
                        throw new \InvalidArgumentException("Invalid relation query");
                    }
                    $relationType = TypeManager::get($relationIdentifier);
                    $relationColumnName = $relation->getProperty($relationColumn)->getName();
                    return "{$relationType->getTable()}.{$relationColumnName}";
                }
            }
        }

        return "{$this->type->getTable()}.{$this->property->getName()}";
    }

    public function getExpression(): string
    {
        return $this->expression;
    }

    public function getProperty(): Property
    {
        return $this->property;
    }

    public function getRelation(): Type
    {
        if ($this->type->isRelationProperty($this->identifier)) {
            return $this->type->getProperty($this->identifier)
                ->getRelation()
                ->getRelationType();
        }

        return $this->type;
    }

    public function getValue(): mixed
    {
        if ($this->isRaw() && \is_array($this->value)) {
            $value = [];
            foreach ($this->value as $k => $v) {
                if ($v instanceof Type) {
                    $value[$k] = $v->getAutoIncrement()->getValue();
                } else {
                    $value[$k] = $v;
                }
            }

            return $value;
        }

        if ($this->value instanceof Type) {
            $normalizedValue = $this->value->getAutoIncrement()->getValue();
        } else {
            $normalizedValue = $this->value;
        }

        return $normalizedValue;
    }

    public function isRaw(): bool
    {
        if (Query::MOD_RAW === $this->expression) {
            return TRUE;
        }

        if (! $this->type->hasProperty($this->identifier)) {
            if (FALSE === $this->byName) {
                return TRUE;
            }

            if (! $this->type->hasPropertyByName($this->identifier)) {
                return TRUE;
            }
        }

        return FALSE;
    }

    protected function getAllowedExpressions(): array
    {
        return [
            Query::MOD_EQ,
            Query::MOD_GT,
            Query::MOD_GTE,
            Query::MOD_IN,
            Query::MOD_ISNOTNULL,
            Query::MOD_ISNULL,
            Query::MOD_LT,
            Query::MOD_LTE,
            Query::MOD_NOTIN,
            Query::MOD_RAW,
        ];
    }

    private function setProperty(): void
    {
        if ($this->isRaw()) {
            return;
        }

        if (! $this->type->hasProperty($this->identifier)) {
            if (FALSE === $this->byName) {
                throw new \InvalidArgumentException("Property $this->identifier not found in type");
            }

            if (! $this->type->hasPropertyByName($this->identifier)) {
                throw new \InvalidArgumentException("Property not found in type");
            }

            $this->property = $this->type->getPropertyByName($this->identifier);
        } else {
            $this->property = $this->type->getProperty($this->identifier);
        }
    }
}
