<?php

declare(strict_types=1);

namespace Marshal\Utils\Database\Validator;

use Laminas\Validator\AbstractValidator;

class PropertyConfigValidator extends AbstractValidator
{
    private const string IDENTIFIER_NOT_FOUND = 'identifierNotFound';
    private const string INVALID_CONTENT_IDENTIFIER = 'invalidContentIdentifier';
    private const string INVALID_INDEX_CONFIG = 'invalidIndexConfig';
    private const string INVALID_PROPERTIES_CONFIGURED = 'invalidPropertiesConfigured';
    private const string INVALID_PROPERTY_NAME = 'invalidPropertyName';
    private const string INVALID_RELATION_CONFIG = 'invalidRelationConfig';
    private const string PROPERY_RELATION_SCHEMA_NOT_SPECIFIED = 'noPropertyRelationSchema';
    private const string PROPERY_RELATION_PROPERTY_NOT_SPECIFIED = 'noPropertyRelationProperty';
    private const string TYPE_NOT_FOUND = 'typeNotFound';
    public array $messageTemplates = [
        self::IDENTIFIER_NOT_FOUND => "Property %value% not found in config",
        self::INVALID_CONTENT_IDENTIFIER => 'Invalid property identifier %value%. Must contain format `prefix::name`',
        self::INVALID_INDEX_CONFIG => 'Invalid index config %value%',
        self::INVALID_PROPERTIES_CONFIGURED => 'Invalid property %value%. Definition must be an array',
        self::INVALID_PROPERTY_NAME => 'Invalid property name %value%',
        self::INVALID_RELATION_CONFIG => 'Invalid relation config %value%',
        self::PROPERY_RELATION_SCHEMA_NOT_SPECIFIED => "Property relation %value% schema key not specified",
        self::PROPERY_RELATION_PROPERTY_NOT_SPECIFIED => "Property relation %value% property key not specified",
        self::TYPE_NOT_FOUND => "Property type %value% not found in config",
    ];

    public function __construct(private array $config)
    {
        parent::__construct();
    }

    public function isValid(mixed $value): bool
    {
        if (! isset($this->config[$value])) {
            $this->setValue($value);
            $this->error(self::IDENTIFIER_NOT_FOUND);
            return FALSE;
        }

        if (! \is_array($this->config[$value])) {
            $this->setValue($value);
            $this->error(self::INVALID_PROPERTIES_CONFIGURED);
            return FALSE;
        }

        $config = $this->config[$value];
        if (! isset($config['label'])) {
            $this->error(self::IDENTIFIER_NOT_FOUND);
            return FALSE;
        }
        if (! isset($config['name'])) {
            $this->error(self::IDENTIFIER_NOT_FOUND);
            return FALSE;
        }
        if (! isset($config['description'])) {
            $this->error(self::IDENTIFIER_NOT_FOUND);
            return FALSE;
        }
        if (! isset($config['type'])) {
            $this->error(self::TYPE_NOT_FOUND);
            return FALSE;
        }

        if (! $this->isValidPropertyIdentifier($value)) {
            return FALSE;
        }

        if (isset($config['index'])) {
            if (! \is_array($config['index']) && ! \is_bool($config['index'])) {
                $this->setValue("on property $value");
                $this->error(self::INVALID_INDEX_CONFIG);
                return FALSE;
            }

            if (! $this->isValidPropertyIndex($config['index'])) {
                return FALSE;
            }
        }

        if (isset($config['relation'])) {
            if (! \is_array($config['relation'])) {
                $this->setValue("on property $value");
                $this->error(self::INVALID_RELATION_CONFIG);
                return FALSE;
            }

            if (! $this->isValidPropertyRelation($value, $config['relation'])) {
                return FALSE;
            }
        }

        return TRUE;
    }

    private function isValidPropertyIdentifier(string $identifier): bool
    {
        $nameParts = \explode('::', $identifier);
        if (\count($nameParts) !== 2) {
            $this->setValue($identifier);
            $this->error(self::INVALID_CONTENT_IDENTIFIER);
            return FALSE;
        }

        return TRUE;
    }

    private function isValidPropertyIndex(array|bool $indexConfig): bool
    {
        return TRUE;
    }

    private function isValidPropertyRelation(string $propertyName, array $relationConfig): bool
    {
        if (! isset($relationConfig['schema']) || ! \is_string($relationConfig['schema'])) {
            $this->setValue(\sprintf("%s", $propertyName));
            $this->error(self::PROPERY_RELATION_SCHEMA_NOT_SPECIFIED);
            return FALSE;
        }

        $split = \explode('::', $relationConfig['schema']);
        if (\count($split) !== 2) {
            $this->setValue(\sprintf("%s", $relationConfig['schema']));
            $this->error(self::INVALID_CONTENT_IDENTIFIER);
            return FALSE;
        }

        if (! isset($relationConfig['property'])) {
            $this->setValue(\sprintf("%s", $propertyName));
            $this->error(self::PROPERY_RELATION_PROPERTY_NOT_SPECIFIED);
            return FALSE;
        }

        return TRUE;
    }
}
