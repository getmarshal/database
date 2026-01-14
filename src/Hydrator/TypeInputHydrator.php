<?php

declare(strict_types=1);

namespace Marshal\Database\Hydrator;

use Marshal\Database\Schema\Type;

final class TypeInputHydrator
{
    public function hydrate(Type $type, array $input): void
    {
        foreach ($input as $key => $value) {
            if (! $type->hasProperty($key)) {
                continue;
            }
            $type->getProperty($key)->setValue($value);
        }
    }
}
