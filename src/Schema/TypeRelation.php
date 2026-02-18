<?php

declare(strict_types=1);

namespace Marshal\Database\Schema;

final class TypeRelation
{
    public const string JOIN_INNER = "joinInner";
    public const string JOIN_LEFT = "joinLeft";
    public const string JOIN_RIGHT = "joinRight";
    public const array UPDATE_DELETE_OPTIONS = ['CASCADE', 'SET NULL'];
    private Type $relationType;

    public function __construct(private readonly string $identifier, private readonly array $config)
    {
    }

    public function getAlias(): string
    {
        return $this->config['relationAlias'] ?? $this->getRelationType()->getTable();
    }

    public function getJoinType(): string
    {
        return $this->config['joinType'] ?? self::JOIN_LEFT;
    }

    public function getLocalProperty(): string
    {
        return $this->config['localProperty'];
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

    public function getRelationCondition(Type $localType): string
    {
        return \sprintf(
            "%s.%s = %s.%s",
            $localType->getTable(),
            $localType->getProperty($this->getLocalProperty())->getName(),
            $this->getAlias(),
            $this->getRelationProperty()->getName(),
        );
    }

    public function getRelationProperty(): Property
    {
        return $this->getRelationType()->getProperty($this->config['relationProperty']);
    }

    public function getRelationType(): Type
    {
        if (! isset($this->relationType)) {
            $this->relationType = TypeManager::get($this->config['relationType']);
        }

        return $this->relationType;
    }

    public function getRelationTypeClass(): string
    {
        return $this->config['relationType'];
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }
}
