<?php

declare(strict_types=1);

namespace Marshal\Database\Event\Content;

use Marshal\Database\QueryBuilder;
use Marshal\Database\Schema\Content;

final class UpdateQueryEvent
{
    use ContentEventTrait;
    use QueryEventTrait;

    public function __construct(private QueryBuilder $query, private Content $content, private array $updates)
    {
    }
}
