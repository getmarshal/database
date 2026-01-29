<?php

declare(strict_types=1);

namespace Marshal\Database\Schema\Validator;

use Laminas\Validator\AbstractValidator;
use Marshal\Database\Schema\Type;

class TypeConfigValidator extends AbstractValidator
{
    private const string DESCRIPTION_NOT_FOUND = 'descriptionNotFound';
    private const string DATABASE_NOT_FOUND = 'databaseNotFound';
    private const string TABLE_NOT_FOUND = 'tableNotFound';
    private const string IDENTIFIER_NOT_FOUND = 'identifierNotFound';
    private const string INVALID_INDEX_CONFIG = 'invalidIndexConfig';
    private const string INVALID_PROPERTIES_CONFIGURED = 'invalidPropertiesConfigured';
    private const string INVALID_RELATIONS_CONFIGURED = 'invalidRelationsConfigured';
    private const string INVALID_RELATIONS_IDENTIFIER = "invalidRelationsIdentifier";
    private const string RELATION_LOCAL_PROPERTY_NOT_SET = "relationLocalPropertyNotSet";
    private const string RELATION_RELATION_TYPE_NOT_SET = "relationRelationTypeNotSet";
    private const string RELATION_RELATION_PROPERY_NOT_SET = "relationRelationPropertyNotSet";
    private const string NAME_NOT_FOUND = 'nameNotFound';
    
    public array $messageTemplates = [
        self::DATABASE_NOT_FOUND => "Type %value% has no database configured",
        self::DESCRIPTION_NOT_FOUND => "Type %value% has no description configured",
        self::TABLE_NOT_FOUND => "Type %value% has no table configured",
        self::IDENTIFIER_NOT_FOUND => "Type  %value% not found in config!",
        self::INVALID_INDEX_CONFIG => 'Invalid index config %value%',
        self::INVALID_PROPERTIES_CONFIGURED => 'Type schema %value% properties empty or not configured',
        self::INVALID_RELATIONS_CONFIGURED => 'Type schema %value% relations config is invalid. Must be an array',
        self::INVALID_RELATIONS_IDENTIFIER => "Invalid relations identifier %value%",
        self::RELATION_LOCAL_PROPERTY_NOT_SET => "Relation localProperty not set: %value%",
        self::RELATION_RELATION_TYPE_NOT_SET => "Relation relationType not set: %value%",
        self::RELATION_RELATION_PROPERY_NOT_SET => "Relation relationProperty not set: %value%",
        self::NAME_NOT_FOUND => "Type %value% has no name configured",
    ];

    public function __construct(private array $config, ?array $options = null)
    {
        parent::__construct($options);
    }

    public function isValid(mixed $value): bool
    {
        if (! isset($this->config[$value])) {
            $this->setValue($value);
            $this->error(self::IDENTIFIER_NOT_FOUND);
            return FALSE;
        }

        $config = $this->config[$value];

        // type name is required
        if (! isset($config['name'])) {
            $this->setValue($value);
            $this->error(self::NAME_NOT_FOUND);
            return FALSE;
        }

        // type description is required
        if (! isset($config['description'])) {
            $this->setValue($value);
            $this->error(self::DESCRIPTION_NOT_FOUND);
            return FALSE;
        }

        // type database is required
        if (! isset($config['database'])) {
            $this->setValue($value);
            $this->error(self::DATABASE_NOT_FOUND);
            return FALSE;
        }

        // type table is required
        if (! isset($config['table'])) {
            $this->setValue($value);
            $this->error(self::TABLE_NOT_FOUND);
            return FALSE;
        }

        if (isset($config['relations'])) {
            if (! \is_array($config['relations'])) {
                $this->setValue($value);
                $this->error(self::INVALID_RELATIONS_CONFIGURED);
                return FALSE;
            }

            if (! $this->validateRelationsConfig($value, $config['relations'])) {
                return FALSE;
            }
        }

        return TRUE;
    }

    private function validateRelationsConfig(string $typeIdentifier, array $config): bool
    {
        foreach ($config as $identifier => $relation) {
            if (! \is_string($identifier)) {
                $this->setValue(\sprintf(
                    "%s on type %s",
                    \get_debug_type($identifier),
                    $typeIdentifier
                ));
                $this->error(self::INVALID_RELATIONS_IDENTIFIER);
                return FALSE;
            }

            if (! isset($relation['localProperty'])) {
                $this->setValue(\sprintf(
                    "%s on type %s",
                    $identifier,
                    $typeIdentifier
                ));
                $this->error(self::RELATION_LOCAL_PROPERTY_NOT_SET);
                return FALSE;
            }

            if (! isset($relation['relationType'])) {
                $this->setValue(\sprintf(
                    "%s on type %s",
                    $identifier,
                    $typeIdentifier
                ));
                $this->error(self::RELATION_RELATION_TYPE_NOT_SET);
                return FALSE;
            }

            if (! isset($relation['relationProperty'])) {
                $this->setValue(\sprintf(
                    "%s on type %s",
                    $identifier,
                    $typeIdentifier
                ));
                $this->error(self::RELATION_RELATION_PROPERY_NOT_SET);
                return FALSE;
            }
        }
        return TRUE;
    }
}
