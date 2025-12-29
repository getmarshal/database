<?php

declare(strict_types=1);

namespace Marshal\Database;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type as DBALType;

final class Property
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
    private PropertyRelation $relation;
    private int $scale = 0;
    private DBALType $type;
    private string $typeName;
    private bool $unsigned = false;
    private array $validators = [];
    private mixed $value = null;

    public function __construct(
        private readonly string $identifier,
        array $definition,
        ?PropertyRelation $relation = null
    ) {
        $this->prepareFromDefinition($definition);
        if (null !== $relation) {
            $this->relation = $relation;
        }
    }

    public function isAutoIncrement(): bool
    {
        return $this->autoIncrement;
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

    public function getDatabaseValue(AbstractPlatform $databasePlatform): mixed
    {
        if (! $this->hasRelation()) {
            return $this->getDatabaseType()->convertToDatabaseValue($this->getValue(), $databasePlatform);
        }

        $relation = $this->getValue();
        if (! $relation instanceof Type) {
            if (
                \is_array($relation)
                && isset($relation[$this->getRelationColumn()])
                && \is_scalar($relation[$this->getRelationColumn()])
            ) {
                return $relation[$this->getRelationColumn()];
            }

            return $relation;
        }

        return $relation->getProperty($this->getRelationColumn())->getDatabaseValue($databasePlatform);
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

    public function getRelation(): PropertyRelation
    {
        if (! $this->hasRelation()) {
            throw new \InvalidArgumentException("Property {$this->getIdentifier()} has no relation");
        }

        return $this->relation;
    }

    public function getRelationProperty(): Property
    {
        return $this->getRelation()->getRelationProperty();
    }

    public function getRelationColumn(): string
    {
        return $this->getRelationProperty()->getName();
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
        if ($this->hasRelation()) {
            return $this->getRelation()->getRelationType();
        }

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

    public function hasRelation(): bool
    {
        return isset($this->relation);
    }

    public function hasUniqueConstraint(): bool
    {
        return isset($this->constraints['unique']) && $this->constraints['unique'] instanceof PropertyConstraint;
    }

    public function hydrate(mixed $value, ?AbstractPlatform $databasePlatform = NULL): void
    {
        if ($this->hasRelation()) {
            if (\is_array($value)) {
                $propertyData = [];
                $relationContentTable = $this->getRelation()->getTable();
                foreach ($value as $k => $v) {
                    $propertyData["{$relationContentTable}__$k"] = $v;
                }
                $this->getRelation()->getRelationType()->hydrate(
                    $propertyData,
                    $databasePlatform,
                    $this->getRelation()->getAlias()
                );
            } elseif (\is_int($value)) {
                $this->getRelation()->getRelationType()->getAutoIncrement()->setValue($value);
            } elseif ($value instanceof self) {
                $this->getRelation()->getRelationProperty()->setValue($value);
            }
        }

        NULL === $databasePlatform || TRUE !== $this->getConvertToPhpType()
            ? $this->setValue($value)
            : $this->setValue(
                $this->getDatabaseType()->convertToPHPValue($value, $databasePlatform)
            );
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
