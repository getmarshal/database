<?php

declare(strict_types=1);

namespace Marshal\Database\Hydrator;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Marshal\Database\Schema\Property;
use Marshal\Database\Schema\Type;

final class DatabaseResultHydrator
{
    public function hydrate(Type $type, array $result, AbstractPlatform $databasePlatform): void
    {
        $data = $this->normalize($result);
        foreach ($data as $key => $value) {
            if ($type->isRelationProperty($key)) {
                $relationType = $type->getRelation($key)->getRelationType();
                if (\is_array($value)) {
                    foreach ($value as $k => $v) {
                        if (! $relationType->hasProperty($k)) {
                            continue;
                        }

                        $this->hydrateProperty($relationType->getProperty($k), $v, $databasePlatform);
                    }
                }

                // set the relation type as the property value
                $type->getProperty($key)->setValue($relationType);

                // repeat hydration for nested relations
                $this->hydrate($relationType, $result, $databasePlatform);
            } elseif ($key === $type->getTable()) {
                if (\is_array($value)) {
                    foreach ($value as $k => $v) {
                        if (! $type->hasProperty($k)) {
                            continue;
                        }

                        $this->hydrateProperty($type->getProperty($k), $v, $databasePlatform);
                    }
                }
            }
        }
    }

    private function hydrateProperty(Property $property, mixed $value, AbstractPlatform $databasePlatform): void
    {
        TRUE !== $property->getConvertToPhpType()
            ? $property->setValue($value)
            : $property->setValue(
                $property->getDatabaseType()->convertToPHPValue($value, $databasePlatform)
            );
    }

    private function normalize(array $result): array
    {
        $data = [];
        foreach ($result as $key => $value) {
            $parts = \explode('__', $key);
            $name = \array_shift($parts);
            $data[$name][\implode('__', $parts)] = $value;
        }

        return $data;
    }
}
