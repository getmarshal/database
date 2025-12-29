<?php

declare(strict_types=1);

namespace Marshal\Database;

final class PropertyRelation
{
    private const array UPDATE_DELETE_OPTIONS = ['CASCADE', 'SET NULL'];

    private Type $relation;

    public function __construct(private readonly array $config)
    {
        $this->relation = TypeManager::get($config['type']);
    }

    public function getAlias(): string
    {
        return $this->config['alias'] ?? $this->getTable();
    }

    public function getOnDelete(): string
    {
        if (! isset($this->config['onDelete'])) {
            return 'CASCADE';
        }

        if (
            ! \is_string($this->config['onDelete'])
            || ! \in_array(\strtoupper($this->config['onDelete']), self::UPDATE_DELETE_OPTIONS, true)
        ) {
            return 'CASCADE';
        }

        return $this->config['onDelete'];
    }

    public function getOnUpdate(): string
    {
        if (! isset($this->config['onUpdate'])) {
            return 'CASCADE';
        }

        if (
            ! \is_string($this->config['onUpdate'])
            || ! \in_array(\strtoupper($this->config['onUpdate']), self::UPDATE_DELETE_OPTIONS, true)
        ) {
            return 'CASCADE';
        }

        return $this->config['onUpdate'];
    }

    public function getRelationProperty(): Property
    {
        return $this->relation->getProperty($this->config['property']);
    }

    public function getRelationType(): Type
    {
        return $this->relation;
    }

    public function getRelationIdentifier(): string
    {
        return $this->relation->getIdentifier();
    }

    /**
     * @return array<string, Property>
     */
    public function getRelationProperties(): array
    {
        return $this->relation->getProperties();
    }

    public function getTable(): string
    {
        return $this->relation->getTable();
    }
}
