<?php

declare(strict_types=1);

namespace Marshal\Database\Event\Content;

use Marshal\Database\Schema\Content;

trait ContentEventTrait
{
    public function getContent(): Content
    {
        return $this->content;
    }
}
