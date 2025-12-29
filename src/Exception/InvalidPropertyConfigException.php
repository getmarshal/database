<?php

declare(strict_types=1);

namespace Marshal\Database\Exception;

class InvalidPropertyConfigException extends \InvalidArgumentException
{
    public function __construct(string $name, array $messages)
    {
        parent::__construct(\sprintf(
            "Invalid property config %s: %s",
            $name,
            \implode(', ', $messages)
        ));
    }
}
