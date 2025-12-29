<?php

declare(strict_types=1);

namespace Marshal\Database;

final class PropertyIndex
{
    public function __construct(private bool|array $definition)
    {
    }

    public function getFlags(): array
    {
        if (! \is_array($this->definition)) {
            return [];
        }

        if (! isset($this->definition['flags']) || ! \is_array($this->definition['flags'])) {
            return [];
        }

        return $this->definition['flags'];
    }

    public function getName(): ?string
    {
        if (! \is_array($this->definition)) {
            return null;
        }

        if (! isset($this->definition['name']) || ! \is_string($this->definition['name'])) {
            return null;
        }

        return $this->definition['name'];
    }

    public function getOptions(): array
    {
        if (! \is_array($this->definition)) {
            return [];
        }

        if (! isset($this->definition['options']) || ! \is_array($this->definition['options'])) {
            return [];
        }

        return $this->definition['options'];
    }
}
