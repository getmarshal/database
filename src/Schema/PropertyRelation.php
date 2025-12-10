<?php

declare(strict_types=1);

namespace Marshal\Utils\Database\Schema;

final class PropertyRelation
{
    private const array UPDATE_DELETE_OPTIONS = ['CASCADE', 'SET NULL'];
    private Property $relatedProperty;

    public function __construct(private Type $relation, private array $config)
    {
        $this->relatedProperty = $relation->getPropertyByIdentifier($config['property']);
    }

    public function getAlias(): string
    {
        return $this->config['alias'] ?? $this->getType()->getTable();
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

    public function getProperty(): Property
    {
        return $this->relatedProperty;
    }

    public function getType(): Type
    {
        return $this->relation;
    }
}
