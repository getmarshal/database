<?php

declare(strict_types=1);

namespace Marshal\Database\Exception;

use Marshal\Utils\Logger\LoggerManager;

final class DatabaseQueryException extends \RuntimeException
{
    public function __construct(\Throwable $exception)
    {
        // log
        LoggerManager::get()->error($exception->getMessage());

        // raise
        parent::__construct($exception->getMessage(), $exception->getCode(), $exception);
    }
}
