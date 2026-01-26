<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Exception;

use Marshal\Database\QueryBuilder;
use Marshal\Utils\Logger\LoggerManager;

final class DatabaseQueryException extends \RuntimeException
{
    public function __construct(\Throwable $exception, QueryBuilder $query)
    {
        // log
        LoggerManager::get()->error($exception->getMessage(), [
            'sql' => $query->getSQL(),
            'parameters' => $query->getParameters()
        ]);

        // raise
        parent::__construct($exception->getMessage(), $exception->getCode(), $exception);
    }
}
