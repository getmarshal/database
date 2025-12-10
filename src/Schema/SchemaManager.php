<?php

declare(strict_types=1);

namespace Marshal\Utils\Database\Schema;

use Marshal\Utils\Database\Exception\InvalidTypeConfigException;
use Marshal\Utils\Database\Exception\InvalidPropertyConfigException;
use Marshal\Utils\Database\Validator\PropertyConfigValidator;
use Marshal\Utils\Database\Validator\TypeConfigValidator;

final class SchemaManager
{
    public function __construct(
        private TypeConfigValidator $typeValidator,
        private PropertyConfigValidator $propertyValidator,
        private array $typesConfig,
        private array $propertiesConfig
    ) {
    }

    public function get($name): Type
    {
        if (! $this->typeValidator->isValid($name)) {
            throw new InvalidTypeConfigException($name, $this->typeValidator->getMessages());
        }

        $nameSplit = \explode('::', $name);

        $type = new Type(
            identifier: $name,
            database: $nameSplit[0],
            table: $nameSplit[1],
            config: $this->typesConfig[$name]
        );

        foreach ($this->typesConfig[$name]['inherits'] ?? [] as $identifier) {
            $type->addParent($this->get($identifier));
        }

        foreach ($this->typesConfig[$name]['properties'] ?? [] as $identifier => $definition) {
            if (! $this->propertyValidator->isValid($identifier)) {
                throw new InvalidPropertyConfigException($name, $this->propertyValidator->getMessages());
            }

            if ($type->hasPropertyIdentifier($identifier)) {
                $property = $type->getPropertyByIdentifier($identifier);
                $property->prepareFromDefinition($definition);
                $type->setProperty($property);
            } else {
                $fullDefinition = \array_merge($this->propertiesConfig[$identifier], $definition);
                if (isset($fullDefinition['relation'])) {
                    $fullDefinition['relation'] = new PropertyRelation(
                        $this->get($fullDefinition['relation']['schema']),
                        $fullDefinition['relation']
                    );
                }

                $type->setProperty(new Property($identifier, $fullDefinition));
            }
        }

        // remove excluded properties
        foreach ($this->typesConfig[$name]['exclude_properties'] ?? [] as $identifier) {
            $type->removeProperty($identifier);
        }

        return $type;
    }

    /**
     * @return Type[]
     */
    public function getAll(): array
    {
        $schema = [];
        foreach (\array_keys($this->typesConfig) as $name) {
            $schema[$name] = $this->get($name);
        }

        return $schema;
    }
}
