<?php

declare(strict_types=1);

namespace Marshal\Database\Exception;

final class InvalidInputException extends \InvalidArgumentException
{
    public function __construct(array $messages)
    {
        parent::__construct(sprintf(
            "Invalid input: %s",
            print_r($messages, true)
        ));
    }
}
