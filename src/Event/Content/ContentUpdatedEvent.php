<?php

declare(strict_types=1);

namespace Marshal\Database\Event\Content;

use Marshal\Database\Schema\Content;

final class ContentUpdatedEvent
{
    use ContentEventTrait;

    public function __construct(private Content $content, private array $updates)
    {
    }

    public function getUpdates(): array
    {
        return $this->updates;
    }
}
