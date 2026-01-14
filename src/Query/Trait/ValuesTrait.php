<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Trait;

trait ValuesTrait
{
    private array $values = [];

    public function set(string $identifier, mixed $value): static
    {        
        if (! $this->type->hasProperty($identifier)) {
            return $this;
        }

        $property = $this->type->getProperty($identifier);
        $this->values[$property->getName()] = $value;
        return $this;
    }

    public function withValues(array $values): static
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }
}
