<?php

declare(strict_types=1);

namespace Marshal\Database\Query\Exception;

use Marshal\Database\QueryBuilder;
use Marshal\Database\Schema\Content;
use Marshal\Utils\Logger\LoggerManager;

final class UpdateQueryException extends \RuntimeException
{
    public function __construct(
        private QueryBuilder $query,
        private Content $content,
        private \Throwable $exception
    ) {
        LoggerManager::get()->error($exception->getMessage(), $content->toArray());
        parent::__construct($exception->getMessage(), $exception->getCode(), $exception);
    }
}
