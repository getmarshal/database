<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Modifier;

use Marshal\Database\QueryBuilder;
use Marshal\Database\Schema\Property;
use Marshal\Database\Schema\Content;

trait Properties
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

    private function applyDistincts(QueryBuilder $queryBuilder, Content $content): void
    {
        if (empty($this->distinct)) {
            return;
        }

        [$typeIdentifier, $propertyIdentifier] = $this->distinct;
        foreach ($this->distinct as $identifier => $properties) {

        }

        if ($content->getSchemaIdentifier() === $typeIdentifier || $content->getTable() === $typeIdentifier) {
            if (! $content->hasProperty($propertyIdentifier)) {
                throw new \InvalidArgumentException(\sprintf(
                    "Invalid distinct query: Property %s not found on type %s",
                    $propertyIdentifier, $typeIdentifier
                ));
            }

            $this->applyTypeDistinctProperty($queryBuilder, $content, $propertyIdentifier);
            return;
        }

        if ($content->isRelationProperty($typeIdentifier)) {
            $relation = $content->getRelation($typeIdentifier);
            $this->applyTypeDistinctProperty($queryBuilder, $relation->getRelationType(), $propertyIdentifier, $relation->getAlias());
            // $this->applyRelationJoin($queryBuilder, $relation);
            return;

        }

        if ($content->getSchemaIdentifier() === $identifier || $content->getTable() === $identifier) {
            $this->applyTypeDistinctProperties($queryBuilder, $content, $properties);
            return;
        }

        throw new \InvalidArgumentException(\sprintf(
            "Invalid distinct query. Unknown distinct identifier %s",
            $typeIdentifier
        ));
    }

    protected function applyProperties(QueryBuilder $queryBuilder, Content $content, ?string $alias = null): void
    {
        $delta = empty($this->properties)
            ? [$content->getSchemaIdentifier() => \array_map(
                static fn (Property $property): string => $property->getName(),
                $content->getProperties()
            )]
            : $this->properties;

        foreach ($delta as $identifier => $properties) {
            if ($content->isRelationProperty($identifier)) {
                $relation = $content->getRelation($identifier);
                $this->applyTypeProperties(
                    $queryBuilder,
                    $relation->getRelationType(),
                    $properties,
                    $relation->getAlias()
                );
                continue;
            }

            if ($content->hasProperty($identifier)) {
                $this->applyTypeProperties($queryBuilder, $content, $properties, $alias);
                $this->excludeProperties($alias ?? $identifier, $properties);
                continue;
            }

            if (
                $identifier === $content->getSchemaIdentifier() ||
                $identifier === $content->getTable()
            ) {
                $this->applyTypeProperties($queryBuilder, $content, $properties, $alias);
                $this->excludeProperties($alias ?? $identifier, $properties);
                continue;
            }

            throw new \InvalidArgumentException(\sprintf(
                "Invalid query. Unknown properties identifier %s",
                $identifier
            ));
        }
    }

    protected function applyTypeDistinctProperty(QueryBuilder $queryBuilder, Content $content, string $identifier, ?string $alias = null): void
    {
        if (! $content->hasProperty($identifier) && null === $alias) {
            throw new \InvalidArgumentException(\sprintf(
                "Distinct property %s not found on type %s",
            ));
        }

        // add the qualfied select
        $table = $alias ?? $content->getTable();
        $name = $content->getProperty($identifier)->getName();
        $queryBuilder->addSelect("DISTINCT {$table}.$name AS {$table}__$name");

        // exclude property from further processing
        $this->excludeProperty($content->getSchemaIdentifier(), $identifier);
    }

    protected function applyTypeProperties(QueryBuilder $queryBuilder, Content $content, array $properties, ?string $alias = null): void
    {
        foreach ($properties as $identifier) {
            if (! $content->hasProperty($identifier)) {
                throw new \InvalidArgumentException(\sprintf(
                    "Property %s not found on type %s",
                    $identifier, $content->getSchemaIdentifier()
                ));
            }

            $property = $content->getProperty($identifier);
            // skip excluded properties by type identifier
            if (\array_key_exists($content->getSchemaIdentifier(), $this->excludeProperties)) {
                if (
                    \in_array($property->getIdentifier(), $this->excludeProperties[$content->getSchemaIdentifier()], true) ||
                    \in_array($property->getName(), $this->excludeProperties[$content->getSchemaIdentifier()], true)
                ) {
                    continue;
                }
            }

            // skip excluded properties by type table name
            if (\array_key_exists($content->getTable(), $this->excludeProperties)) {
                if (
                    \in_array($property->getIdentifier(), $this->excludeProperties[$content->getTable()], true) ||
                    \in_array($property->getName(), $this->excludeProperties[$content->getTable()], true)
                ) {
                    continue;
                }
            }

            $table = $alias ?? $content->getTable();
            if ($content->isRelationProperty($property->getIdentifier())) {
                $table = $content->getRelation($property->getIdentifier())->getAlias();
            }

            // add the select
            if ($content->isRelationProperty($property->getIdentifier())) {
                $relation = $content->getRelation($property->getIdentifier());
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
