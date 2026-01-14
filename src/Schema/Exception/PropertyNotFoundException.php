<?php

declare(strict_types=1);

namespace Marshal\Database\Schema\Exception;

final class PropertyNotFoundException extends \InvalidArgumentException
{
    public function __construct(string $name)
    {
        parent::__construct("Property $name not found in config");
    }
}
