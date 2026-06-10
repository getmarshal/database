<?php

/*
Copyright (C) 2026 Collins Pamba

This file is part of Marshal and Marshal is free software:
you can redistribute it and/or modify it under the terms of the GNU Affero General Public License as published by the Free Software Foundation,
either version 3 of the License, or (at your option) any later version.
*/

declare(strict_types=1);

namespace Marshal\Database\Schema;

use Marshal\Utils\Config;

final class ContentManager
{
    private function __construct()
    {
    }

    private function __clone(): void
    {
    }

    public static function get($identifier): Content
    {
        $schema = Config::get('schema');
        $typesConfig = $schema['types'] ?? [];

        if (! \class_exists($identifier)) {
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
        $class = \class_exists($identifier) ? $identifier : Content::class;
        try {
            $content = new $class($identifier, new ContentConfig($config));
        } catch (\Throwable $e) {
            throw new \RuntimeException($e->getMessage(), $e->getCode(), $e);
        }

        if (! $content instanceof Content) {
            throw new \RuntimeException(\sprintf(
                "Expected an instance of %s, given %s instead",
                Content::class,
                \get_debug_type($content)
            ));
        }
        
        // add type properties
        $propsConfig = $schema['properties'] ?? [];
        $propertyValidator = new Validator\PropertyConfigValidator($propsConfig, $typesConfig);
        foreach ($config['properties'] ?? [] as $propertyIdentifier) {
            if (! isset($propsConfig[$propertyIdentifier])) {
                throw new Exception\PropertyNotFoundException($propertyIdentifier);
            }

            // validate property config
            if (! $propertyValidator->isValid($propertyIdentifier)) {
                throw new Exception\InvalidPropertyConfigException($propertyIdentifier, $propertyValidator->getMessages());
            }

            $content->setProperty(new Property($propertyIdentifier, $propsConfig[$propertyIdentifier]));
        }

        // add type relations
        foreach ($config['relations'] ?? [] as $relationIdentifier => $relationConfig) {
            $content->addRelation(new ContentRelation(
                identifier: $relationIdentifier,
                config: $relationConfig
            ));
        }

        return $content;
    }
}
