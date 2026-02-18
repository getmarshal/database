<?php

declare(strict_types=1);

namespace Marshal\Database\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type as DBALType;

class Property
{
    private bool $autoIncrement = false;
    /**
     * @var array<string, PropertyConstraint>
     */
    private array $constraints = [];
    private bool $convertToPhpType = true;
    private mixed $default = null;
    private string $description;
    private array $filters = [];
    private bool $fixed = false;
    private PropertyIndex $index;
    private string $label;
    private ?int $length = null;
    private string $name;
    private bool $notNull = false;
    private array $platformOptions = [];
    private int $precision = 10;
    private int $scale = 0;
    private DBALType $type;
    private string $typeName;
    private bool $unsigned = false;
    private array $validators = [];
    private mixed $value = null;

    public function __construct(private readonly string $identifier, array $definition)
    {
        $this->prepareFromDefinition($definition);
    }

    public function isAutoIncrement(): bool
    {
        return $this->autoIncrement;
    }

    public function convertToDatabaseValue(AbstractPlatform $databasePlatform): mixed
    {
        $value = $this->value;
        if ($value instanceof Type) {
            $value = $value->getAutoIncrement()->getValue();
        }

        if ($this->typeName === 'bigint' && \is_array($value) && isset($value['id'])) {
            $value = \intval($value['id']);
        }

        if (\is_object($value) && \method_exists($value, 'getType')) {
            $type = $value->getType();
            if ($type instanceof Type) {
                $value = $type->getAutoIncrement()->getValue();
            }
        }

        return $this->getDatabaseType()->convertToDatabaseValue($value, $databasePlatform);
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getConvertToPhpType(): bool
    {
        return $this->convertToPhpType;
    }

    public function getDatabaseType(): DBALType
    {
        return $this->type;
    }

    public function getDatabaseTypeName(): string
    {
        return $this->typeName;
    }

    public function getDefaultValue(): mixed
    {
        return $this->default;
    }

    public function getFilters(): array
    {
        return $this->filters;
    }

    public function getFixed(): bool
    {
        return $this->fixed;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getIndex(): PropertyIndex
    {
        return $this->index;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getLength(): ?int
    {
        return $this->length;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNotNull(): bool
    {
        return $this->notNull;
    }

    public function getPlatformOptions(): array
    {
        return $this->platformOptions;
    }

    public function getPrecision(): int
    {
        return $this->precision;
    }

    public function getScale(): int
    {
        return $this->scale;
    }

    public function getUniqueConstraint(): PropertyConstraint
    {
        return $this->constraints['unique'];
    }

    public function getUnsigned(): bool
    {
        return $this->unsigned;
    }

    public function getValidators(): array
    {
        return $this->validators;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function hasDescription(): bool
    {
        return isset($this->comment);
    }

    public function hasIndex(): bool
    {
        return isset($this->index);
    }

    public function hasUniqueConstraint(): bool
    {
        return isset($this->constraints['unique']) && $this->constraints['unique'] instanceof PropertyConstraint;
    }

    public function prepareFromDefinition(array $definition): void
    {
        // @todo validate $definition items in PropertyConfigValidator
        isset($definition['type']) && $this->type = DBALType::getType($definition['type']);
        isset($definition['type']) && $this->typeName = $definition['type'];
        isset($definition['label']) && $this->label = $definition['label'];
        isset($definition['name']) && $this->name = $definition['name'];
        isset($definition['default']) && $this->default = $definition['default'];
        isset($definition['description']) && $this->description = $definition['description'];
        isset($definition['autoincrement']) && $this->autoIncrement = \boolval($definition['autoincrement']);
        isset($definition['notnull']) && $this->notNull = \boolval($definition['notnull']);
        isset($definition['platformOptions']) && $this->platformOptions = (array) $definition['platformOptions'];
        isset($definition['fixed']) && $this->fixed = \boolval($definition['fixed']);
        isset($definition['length']) && \is_int($definition['length']) && $this->length = $definition['length'];
        isset($definition['precision']) && $this->precision = \intval($definition['precision']);
        isset($definition['scale']) && $this->scale = \intval($definition['scale']);
        isset($definition['unsigned']) && $this->unsigned = \boolval($definition['unsigned']);
        isset($definition['scale']) && $this->scale = \intval($definition['scale']);
        isset($definition['convertToPhpType']) && \is_bool($definition['convertToPhpType']) && $this->convertToPhpType = $definition['convertToPhpType'];
        isset($definition['index']) && (\is_array($definition['index']) || \is_bool($definition['index'])) && $this->index = new PropertyIndex($definition['index']);

        // setup constraints
        if (isset($definition['constraints']) && \is_array($definition['constraints'])) {
            foreach ($definition['constraints'] as $type => $constraintDefinition) {
                $this->constraints[$type] = new PropertyConstraint($type, $constraintDefinition);
            }
        }

        // setup input filters
        foreach ($definition['filters'] ?? [] as $filter => $options) {
            $this->filters[$filter] = $options;
        }

        // setup validators
        foreach ($definition['validators'] ?? [] as $validator => $options) {
            $this->validators[$validator] = $options;
        }
    }

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }
}
