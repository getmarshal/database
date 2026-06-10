<?php

declare(strict_types=1);

namespace Marshal\Database\Exception;

use Marshal\Database\QueryBuilder;
use Marshal\Database\Schema\Type;

final class UpdateQueryException extends \RuntimeException
{
    public function __construct(
        private QueryBuilder $query,
        private Type $type,
        private \Throwable $exception
    ) {
        parent::__construct($exception->getMessage(), $exception->getCode(), $exception);
    }
}
