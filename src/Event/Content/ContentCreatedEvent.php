<?php

declare(strict_types=1);

namespace Marshal\Database\Event\Content;

use Marshal\Database\Schema\Content;

final class ContentCreatedEvent
{
    use ContentEventTrait;

    public function __construct(private Content $content)
    {
    }
}
