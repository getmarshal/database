<?php

declare(strict_types=1);

namespace Marshal\Database\Hydrator;

use Marshal\Database\Schema\Content;

final class ItemInputHydrator
{
    public function hydrate(Content $content, array $input): void
    {
        foreach ($input as $key => $value) {
            if (! $content->hasProperty($key)) {
                continue;
            }

            $content->getProperty($key)->setValue($value);
        }
    }
}
