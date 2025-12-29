<?php

declare(strict_types=1);

namespace Marshal\Database;

use Marshal\Utils\Config;

final class TypeManager
{
    private function __construct()
    {
    }

    private function __clone(): void
    {
    }

    public static function get($identifier): Type
    {
        $schema = Config::get('schema');
        $typesConfig = $schema['types'] ?? [];

        // validate the type
        $typeValidator = new Validator\TypeConfigValidator($typesConfig);
        if (! $typeValidator->isValid($identifier)) {
            throw new Exception\InvalidTypeConfigException($identifier, $typeValidator->getMessages());
        }

        $config = $typesConfig[$identifier];
        if (isset($config['wrapperClass'])) {
            if (! \class_exists($config['wrapperClass'])) {
                throw new \InvalidArgumentException("Wrapper class does not exist");
            }

            if (! \is_subclass_of($config['wrapperClass'], Type::class)) {
                throw new \RuntimeException("Wrapper class not a subclass of Type");
            }
            
            $type = new $config['wrapperClass'](
                identifier: $identifier,
                database: $config['database'] ?? \explode('::', $identifier)[0],
                table: $config['table'] ?? \explode('::', $identifier)[1],
                config: $config
            );
        } else {
            $type = new Type(
                identifier: $identifier,
                database: $config['database'] ?? \explode('::', $identifier)[0],
                table: $config['table'] ?? \explode('::', $identifier)[1],
                config: $config
            );
        }

        // add type properties
        $propsConfig = $schema['properties'] ?? [];
        $propertyValidator = new Validator\PropertyConfigValidator($propsConfig, $typesConfig);
        foreach ($config['properties'] ?? [] as $propertyIdentifier) {
            if (! isset($propsConfig[$propertyIdentifier])) {
                throw new Exception\PropertyNotFoundException($propertyIdentifier);
            }

            // check if property has parent
            if (isset($propsConfig[$propertyIdentifier]['inherits'])) {
                $parent = $propsConfig[$propertyIdentifier]['inherits'];
                if (! isset($propsConfig[$parent])) {
                    throw new Exception\PropertyNotFoundException($parent);
                }

                $mergedConfig = $propsConfig[$parent];
                foreach ($propsConfig[$propertyIdentifier]['override'] ?? [] as $key => $value) {
                    $mergedConfig[$key] = $value;
                }

                $propsConfig[$propertyIdentifier] = $mergedConfig;
                $propertyValidator = new Validator\PropertyConfigValidator($propsConfig, $typesConfig);
            }

            // validate property config
            if (! $propertyValidator->isValid($propertyIdentifier)) {
                throw new Exception\InvalidPropertyConfigException($propertyIdentifier, $propertyValidator->getMessages());
            }

            $propertyDefinition = $propsConfig[$propertyIdentifier];
            $propertyRelation = isset($propertyDefinition['relation'])
                ? new PropertyRelation($propertyDefinition['relation'])
                : null;
            $property = new Property($propertyIdentifier, $propertyDefinition, $propertyRelation);
            $type->setProperty($property);
        }

        return $type;
    }
}
