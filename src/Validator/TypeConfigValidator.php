<?php

declare(strict_types=1);

namespace Marshal\Database\Validator;

use Laminas\Validator\AbstractValidator;

class TypeConfigValidator extends AbstractValidator
{
    private const string DESCRIPTION_NOT_FOUND = 'descriptionNotFound';
    private const string IDENTIFIER_NOT_FOUND = 'identifierNotFound';
    private const string INVALID_TYPE_INDENTIFIER = 'invalidTypeIdentifier';
    private const string INVALID_INDEX_CONFIG = 'invalidIndexConfig';
    private const string INVALID_PROPERTIES_CONFIGURED = 'invalidPropertiesConfigured';
    private const string NAME_NOT_FOUND = 'nameNotFound';
    
    public array $messageTemplates = [
        self::DESCRIPTION_NOT_FOUND => "Type identifier %value% has no description configured",
        self::IDENTIFIER_NOT_FOUND => "Type identifier %value% not found in config",
        self::INVALID_TYPE_INDENTIFIER => 'Invalid type identifier %value%. Must contain format `database::table`',
        self::INVALID_INDEX_CONFIG => 'Invalid index config %value%',
        self::INVALID_PROPERTIES_CONFIGURED => 'Type schema %value% properties empty or not configured',
        self::NAME_NOT_FOUND => "Type identifier %value% has no name configured",
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

        if (! $this->isValidTypeIdentifier($value)) {
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

        return TRUE;
    }

    private function isValidTypeIdentifier(string $identifier): bool
    {
        $nameParts = \explode('::', $identifier);
        if (\count($nameParts) !== 2) {
            $this->setValue($identifier);
            $this->error(self::INVALID_TYPE_INDENTIFIER);
            return FALSE;
        }

        return TRUE;
    }
}
