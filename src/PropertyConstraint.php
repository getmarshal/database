<?php

declare(strict_types=1);

namespace Marshal\Database;

final class PropertyConstraint
{
    private const array ALLOWED_TYPES = ['unique'];

    public function __construct(private string $type, private array|bool $definition)
    {
        if (! \in_array($type, self::ALLOWED_TYPES, TRUE)) {
            throw new \InvalidArgumentException(
                "Invalid constraint type $type"
            );
        }
    }

    public function getFlags(): array
    {
        if (! \is_array($this->definition)) {
            return [];
        }

        return ! isset($this->definition['flags']) || ! \is_array($this->definition['flags'])
            ? []
            : $this->definition['flags'];
    }

    public function getOptions(): array
    {
        if (! \is_array($this->definition)) {
            return [];
        }

        return ! isset($this->definition['options']) || ! \is_array($this->definition['options'])
            ? []
            : $this->definition['options'];
    }

    public function getName(): ?string
    {
        if (! \is_array($this->definition)) {
            return null;
        }

        return ! isset($this->definition['name']) || ! \is_string($this->definition['name'])
            ? null
            : $this->definition['name'];
    }
}
