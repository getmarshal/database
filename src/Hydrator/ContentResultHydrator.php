<?php

declare(strict_types=1);

namespace Marshal\Database\Hydrator;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Marshal\Database\Schema\Property;
use Marshal\Database\Schema\Content;

final class ContentResultHydrator
{
    public function hydrate(Content $item, array $result, AbstractPlatform $databasePlatform): void
    {
        $data = $this->normalize($result);
        foreach ($data as $key => $value) {
            if ($item->isRelationProperty($key)) {
                $relation = $item->getRelation($key);
                $relationType = $relation->getRelationType();
                if (\is_array($value)) {
                    foreach ($value as $k => $v) {
                        if (! $relationType->hasProperty($k)) {
                            continue;
                        }

                        $this->hydrateProperty($relationType->getProperty($k), $v, $databasePlatform);
                    }
                }

                // set the relation item as the property value
                $relationItem = $relation->getRelationType();
                $item->getProperty($key)->setValue($relationItem);

                // repeat hydration for nested relations
                $this->hydrate($relationItem, $result, $databasePlatform);
            } elseif ($key === $item->getTable()) {
                if (\is_array($value)) {
                    foreach ($value as $k => $v) {
                        if (! $item->hasProperty($k)) {
                            continue;
                        }

                        $this->hydrateProperty($item->getProperty($k), $v, $databasePlatform);
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
