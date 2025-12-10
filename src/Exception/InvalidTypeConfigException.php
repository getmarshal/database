<?php

declare(strict_types=1);

namespace Marshal\Utils\Database\Exception;

class InvalidTypeConfigException extends \InvalidArgumentException
{
    public function __construct(string $name, array $messages)
    {
        parent::__construct(\sprintf(
            "Invalid content config %s: %s",
            $name,
            \implode(', ', $messages)
        ));
    }
}
