<?php

declare(strict_types=1);

namespace Marshal\Database\Schema;

class Content
{
    /* properties */
    public const string ALIAS = "content::alias";
    public const string CREATED_AT = "content::created_at";
    public const string DESCRIPTION = "content::description";
    public const string ID = "content::id";
    public const string IMAGE = "content::image";
    public const string NAME = "content::name";
    public const string TAG = "content::tag";
    public const string UPDATED_AT = "content::updated_at";
    public const string URL = "content::url";
    /**
     * @var array<string, Property>
     */
    private array $properties = [];
    private array $relations = [];

    final public function __construct(private string $identifier, private ContentConfig $config)
    {
    }

    public function __tostring(): string
    {
        return (string) $this->getId();
    }

    public function addRelation(ContentRelation $relation): void
    {
        $this->relations[$relation->getIdentifier()] = $relation;
    }

    public function getAutoIncrement(): Property
    {
        foreach ($this->getProperties() as $property) {
            if ($property->isAutoIncrement()) {
                return $property;
            }
        }

        throw new \InvalidArgumentException("no autoincrement property");
    }

    public function getContentConfig(): ContentConfig
    {
        return $this->config;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->getPropertyValue(self::CREATED_AT);
    }

    public function getSchemaIdentifier(): string
    {
        return $this->identifier;
    }

    public function getId(): int
    {
        return $this->getPropertyValue(self::ID);
    }

    public function getName(): ?string
    {
        return $this->getPropertyValue(self::NAME);
    }

    /**
     * @return array<string, Property>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getProperty(string $identifier): Property
    {
        foreach ($this->getProperties() as $property) {
            if ($identifier === $property->getIdentifier() || $identifier === $property->getName()) {
                return $property;
            }
        }

        throw new \InvalidArgumentException(
            \sprintf("Property %s does not exist in type: %s", $identifier, $this->getSchemaIdentifier())
        );
    }

    public function getPropertyValue(string $property): mixed
    {
        return $this->getProperty($property)->getValue();
    }

    public function getRelation(string $identifier): ContentRelation
    {
        if (isset($this->relations[$identifier])) {
            return $this->relations[$identifier];
        }

        // search local properties
        foreach ($this->getRelations() as $relation) {
            $localProperty = $this->getProperty($relation->getLocalProperty());
            if (
                $identifier === $localProperty->getName() ||
                $identifier === $localProperty->getIdentifier()
            ) {
                return $relation;
            }
        }

        throw new \InvalidArgumentException(\sprintf(
            "Relation %s does not exist on type %s",
            $identifier,
            $this->getSchemaIdentifier()
        ));
    }

    /**
     * @return array<string, ContentRelation>
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    public function getTable(): string
    {
        return $this->config->getTable();
    }

    public function getTag(): string
    {
        return $this->getPropertyValue(self::TAG);
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->getPropertyValue(self::UPDATED_AT);
    }

    public function hasProperty(string $identifier): bool
    {
        foreach ($this->getProperties() as $property) {
            if ($identifier === $property->getIdentifier() || $identifier === $property->getName()) {
                return TRUE;
            }
        }

        return FALSE;
    }

    public function isEmpty(): bool
    {
        foreach ($this->getProperties() as $property) {
            if (true === $property->getNotNull() && null === $property->getValue()) {
                return true;
            }
        }

        return false;
    }

    public function isRelationProperty(string $identifier): bool
    {
        if (! $this->hasProperty($identifier)) {
            return FALSE;
        }

        foreach ($this->getRelations() as $relation) {
            $localProperty = $this->getProperty($relation->getLocalProperty());
            if (
                $localProperty->getIdentifier() === $identifier ||
                $localProperty->getName() === $identifier ||
                $relation->getAlias() === $identifier
            ) {
                return TRUE;
            }
        }

        return FALSE;
    }

    public function setProperty(Property $property): static
    {
        $this->properties[$property->getIdentifier()] = $property;
        return $this;
    }

    public function toArray(): array
    {
        $values = [];
        foreach ($this->getProperties() as $property) {
            $value = $property->getValue();
            $values[$property->getName()] = $value instanceof self
                ? $value->toArray()
                : $value;
        }

        return $values;
    }
}
