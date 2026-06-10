<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Modifier;

use Marshal\Utils\Logger\LoggerManager;

trait Set
{
    private array $values = [];

    public function set(string $identifier, mixed $value): static
    {
        if (! $this->content->hasProperty($identifier)) {
            LoggerManager::get()->warning(\sprintf(
                "Invalid set operation. Property %s not found in Content %s",
                $identifier, $this->content->getSchemaIdentifier()
            ));
            return $this;
        }

        $property = $this->content->getProperty($identifier);
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
