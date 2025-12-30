<?php

declare(strict_types=1);

namespace Marshal\Database\Hydrator;

use Marshal\Database\Type;

final class TypeInputHydrator
{
    public function hydrate(Type $type, array $input): Type
    {
        $type->hydrate($this->normalizeInput($type, $input));
        return $type;
    }

    private function normalizeInput(Type $type, array $input): array
    {
        $data = [];
        foreach ($input as $key => $value) {
            if ($type->hasProperty($key)) {
                $data["{$type->getTable()}__$key"] = $this->normalizeValue($value);
            }
        }

        return $data;
    }

    private function normalizeValue(mixed $value): mixed
    {
        return $value instanceof Type
            ? $value->getAutoIncrement()->getValue()
            : $value;
    }
}
