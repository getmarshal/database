<?php

declare(strict_types=1);

namespace Marshal\Database\Schema;

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

        // check if using table name as identifier
        if (! isset($typesConfig[$identifier])) {
            foreach ($typesConfig as $id => $config) {
                if (isset($config['table']) && $config['table'] === $identifier) {
                    return self::get($id);
                }
            }
        }

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
                throw new \InvalidArgumentException("Wrapper class not a subclass of Type");
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

            $type->setProperty(new Property($propertyIdentifier, $propsConfig[$propertyIdentifier]));
        }

        // add type relations
        foreach ($config['relations'] ?? [] as $relationIdentifier => $relationConfig) {
            $relationType = self::get($relationConfig['relationType']);
            $type->addRelation(new TypeRelation(
                localType: $type,
                localProperty: $type->getProperty($relationConfig['localProperty']),
                relationType: $relationType,
                relationProperty: $relationType->getProperty($relationConfig['relationProperty']),
                identifier: $relationIdentifier,
                config: $relationConfig
            ));
        }

        return $type;
    }
}
