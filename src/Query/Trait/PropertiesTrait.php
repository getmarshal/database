<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Trait;

use Marshal\Database\QueryBuilder;
use Marshal\Database\Schema\Property;
use Marshal\Database\Schema\Type;

trait PropertiesTrait
{
    private array $distinct = [];
    private array $excludeProperties = [];
    private array $properties = [];

    public function addProperty(string $identifier, string $property): static
    {
        if (! \array_key_exists($identifier, $this->properties)) {
            $this->properties[$identifier] = [$property];
            return $this;
        }

        if (isset($this->properties[$identifier][$property])) {
            return $this;
        }

        $this->properties[$identifier][] = $property;
        return $this;
    }

    public function distinct(string $identifier, string $property): static
    {
        $this->distinct = [$identifier, $property];
        return $this;
    }

    public function excludeProperties(string $identifier, array $properties): static
    {
        $this->excludeProperties[$identifier] = $properties;
        return $this;
    }

    public function excludeProperty(string $identifier, string $property): static
    {
        $this->excludeProperties[$identifier][] = $property;
        return $this;
    }

    public function properties(string $identifier, array $properties): static
    {
        $this->properties[$identifier] = $properties;
        return $this;
    }

    private function applyDistincts(QueryBuilder $queryBuilder, Type $type): void
    {
        if (empty($this->distinct)) {
            return;
        }

        [$typeIdentifier, $propertyIdentifier] = $this->distinct;
        foreach ($this->distinct as $identifier => $properties) {
        
        }

        if ($type->getIdentifier() === $typeIdentifier || $type->getTable() === $typeIdentifier) {
            if (! $type->hasProperty($propertyIdentifier)) {
                throw new \InvalidArgumentException(\sprintf(
                    "Invalid distinct query: Property %s not found on type %s",
                    $propertyIdentifier, $typeIdentifier
                ));
            }

            $this->applyTypeDistinctProperty($queryBuilder, $type, $propertyIdentifier);
            return;
        }

        if ($type->isRelationProperty($typeIdentifier)) {
            $relation = $type->getRelation($typeIdentifier);
            $this->applyTypeDistinctProperty($queryBuilder, $relation->getRelationType(), $propertyIdentifier, $relation->getAlias());
            // $this->applyRelationJoin($queryBuilder, $relation);
            return;

        }
        
        if ($type->getIdentifier() === $identifier || $type->getTable() === $identifier) {
            $this->applyTypeDistinctProperties($queryBuilder, $type, $properties);
            return;
        }

        throw new \InvalidArgumentException(\sprintf(
            "Invalid distinct query. Unknown distinct identifier %s",
            $typeIdentifier
        ));
    }

    protected function applyProperties(QueryBuilder $queryBuilder, Type $type, ?string $alias = null): void
    {
        $delta = empty($this->properties)
            ? [$type->getIdentifier() => \array_map(
                static fn (Property $property): string => $property->getName(),
                $type->getProperties()
            )]
            : $this->properties;

        foreach ($delta as $identifier => $properties) {
            if ($type->isRelationProperty($identifier)) {
                $relation = $type->getRelation($identifier);
                $this->applyTypeProperties(
                    $queryBuilder,
                    $relation->getRelationType(),
                    $properties,
                    $relation->getAlias()
                );
                continue;
            }

            if ($type->hasProperty($identifier)) {
                $this->applyTypeProperties($queryBuilder, $type, $properties, $alias);
                $this->excludeProperties($alias ?? $identifier, $properties);
                continue;
            }

            if (
                $identifier === $type->getIdentifier() ||
                $identifier === $type->getTable()
            ) {
                $this->applyTypeProperties($queryBuilder, $type, $properties, $alias);
                $this->excludeProperties($alias ?? $identifier, $properties);
                continue;
            }
            
            throw new \InvalidArgumentException(\sprintf(
                "Invalid query. Unknown properties identifier %s",
                $identifier
            ));
        }
    }

    protected function applyTypeDistinctProperty(QueryBuilder $queryBuilder, Type $type, string $identifier, ?string $alias = null): void
    {
        if (! $type->hasProperty($identifier) && null === $alias) {
            throw new \InvalidArgumentException(\sprintf(
                "Distinct property %s not found on type %s",
            ));
        }

        // add the qualfied select
        $table = $alias ?? $type->getTable();
        $name = $type->getProperty($identifier)->getName();
        $queryBuilder->addSelect("DISTINCT {$table}.$name AS {$table}__$name");

        // exclude property from further processing
        $this->excludeProperty($type->getIdentifier(), $identifier);
    }

    protected function applyTypeProperties(QueryBuilder $queryBuilder, Type $type, array $properties, ?string $alias = null): void
    {
        foreach ($properties as $identifier) {
            if (! $type->hasProperty($identifier)) {
                throw new \InvalidArgumentException(\sprintf(
                    "Property %s not found on type %s",
                    $identifier, $type->getIdentifier()
                ));
            }

            $property = $type->getProperty($identifier);            
            // skip excluded properties by type identifier
            if (\array_key_exists($type->getIdentifier(), $this->excludeProperties)) {
                if (
                    \in_array($property->getIdentifier(), $this->excludeProperties[$type->getIdentifier()], true) ||
                    \in_array($property->getName(), $this->excludeProperties[$type->getIdentifier()], true)
                ) {
                    continue;
                }
            }

            // skip excluded properties by type table name
            if (\array_key_exists($type->getTable(), $this->excludeProperties)) {
                if (
                    \in_array($property->getIdentifier(), $this->excludeProperties[$type->getTable()], true) ||
                    \in_array($property->getName(), $this->excludeProperties[$type->getTable()], true)
                ) {
                    continue;
                }
            }

            $table = $alias ?? $type->getTable();
            if ($type->isRelationProperty($property->getIdentifier())) {
                $table = $type->getRelation($property->getIdentifier())->getAlias();
            }

            // add the select
            if ($type->isRelationProperty($property->getIdentifier())) {
                $relation = $type->getRelation($property->getIdentifier());
                $this->applyProperties($queryBuilder, $relation->getRelationType(), $relation->getAlias());
            } else {
                $queryBuilder->addSelect("{$table}.{$property->getName()} AS {$table}__{$property->getName()}");
            }
        }
    }

    private function isExcludedProperty(string $typeIdentifier, Property $property): bool
    {
        return \in_array($property->getIdentifier(), $this->excludeProperties[$typeIdentifier], true) ||
            \in_array($property->getName(), $this->excludeProperties[$typeIdentifier], true);
    }
}
