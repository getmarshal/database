<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Exception;

use Marshal\Utils\Logger\LoggerManager;

final class InvalidInputException extends \InvalidArgumentException
{
    public function __construct(array $messages)
    {
        LoggerManager::get()->error("Type input error", $messages);
        parent::__construct(\sprintf("Type input error: %s", \implode(', ', $messages)));
    }
}
