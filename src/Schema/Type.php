<?php

declare(strict_types= 1);

namespace Marshal\Database\Schema;

use Laminas\Filter\FilterChain;
use Laminas\Filter\FilterPluginManager;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Validator\ValidatorChain;
use Laminas\Validator\ValidatorPluginManager;
use Marshal\Utils\Config;

final class Type
{
    /**
     * @var array<string, Property>
     */
    private array $properties = [];
    private array $relations = [];
    private TypeConfig $typeConfig;
    private array $validationGroup = [];
    private array $validationMessages = [];

    final public function __construct(private readonly string $identifier, private readonly array $config)
    {
        $this->typeConfig = new TypeConfig($config);
    }
    
    public function __tostring(): string
    {
        return (string) $this->getAutoIncrement()->getValue();
    }

    public function addRelation(TypeRelation $relation): void
    {
        $this->relations[$relation->getIdentifier()] = $relation;
    }

    public function getAutoIncrement(): Property
    {
        foreach ($this->properties as $property) {
            if ($property->isAutoIncrement()) {
                return $property;
            }
        }

        throw new \InvalidArgumentException("no autoincrement property");
    }

    public function getDatabase(): string
    {
        return $this->config['database'];
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getTypeConfig(): TypeConfig
    {
        return $this->typeConfig;
    }

    /**
     * @return array<string, Property>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function getProperty(string $identifier): Property
    {
        foreach ($this->getProperties() as $property) {
            if ($identifier === $property->getIdentifier() || $identifier === $property->getName()) {
                return $property;
            }
        }
        
        throw new \InvalidArgumentException(
            \sprintf("Property %s does not exist in type: %s", $identifier, $this->getIdentifier())
        );
    }

    public function getRelation(string $identifier): TypeRelation
    {
        if (isset($this->relations[$identifier])) {
            return $this->relations[$identifier];
        }

        // search local properties
        foreach ($this->getRelations() as $relation) {
            $localProperty = $this->getProperty($relation->getLocalProperty());
            if (
                $identifier === $localProperty->getName() ||
                $identifier === $localProperty->getIdentifier()
            ) {
                return $relation;
            }
        }

        throw new \InvalidArgumentException(\sprintf(
            "Relation %s does not exist on type %s",
            $identifier,
            $this->getIdentifier()
        ));
    }

    /**
     * @return array<TypeRelation>
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    public function getTable(): string
    {
        return $this->config['table'];
    }

    public function getValidationMessages(): array
    {
        return $this->validationMessages;
    }

    public function getValidators(): array
    {
        return $this->config['validators'] ?? [];
    }

    public function hasProperty(string $identifier): bool
    {
        foreach ($this->getProperties() as $property) {
            if ($identifier === $property->getIdentifier() || $identifier === $property->getName()) {
                return TRUE;
            }
        }
        
        return FALSE;
    }

    public function isRelationProperty(string $identifier): bool
    {
        if (! $this->hasProperty($identifier)) {
            return FALSE;
        }

        foreach ($this->getRelations() as $relation) {
            $localProperty = $this->getProperty($relation->getLocalProperty());
            if (
                $localProperty->getIdentifier() === $identifier ||
                $localProperty->getName() === $identifier ||
                $relation->getAlias() === $identifier
            ) {
                return TRUE;
            }
        }

        return FALSE;
    }

    public function isValid(string $operation): bool
    {
        // create our validator plugin manager
        $validatorManager = new ValidatorPluginManager(
            new ServiceManager(),
            ['dependencies' => Config::get('validators')]
        );

        // validate individual properties
        $inputFilter = $this->getPropertiesInputFilter($validatorManager);
        if (! $inputFilter->setData($this->toArray())->isValid()) {
            foreach ($inputFilter->getMessages() as $key => $message) {
                $this->validationMessages[$key] = $message;
            }
        }

        // chain type level validators
        $validators = $this->getValidators();
        if (! empty($validators)) {
            $chain = $validatorManager->get(ValidatorChain::class);
            \assert($chain instanceof ValidatorChain);
            foreach ($validators as $validator => $options) {
                $options['__operation'] = $operation;
                $chain->attach(
                    $validatorManager->get($validator, $options),
                    $options['break_chain_on_failure'] ?? false,
                    $options['priority'] ?? ValidatorChain::DEFAULT_PRIORITY
                );
            }

            // validate the type
            if (! $chain->isValid($inputFilter->getValues())) {
                foreach ($chain->getMessages() as $key => $message) {
                    $this->validationMessages[$key] = $message;
                }
            }
        }
        
        return empty($this->validationMessages);
    }

    public function setProperty(Property $property): static
    {
        $this->properties[$property->getIdentifier()] = $property;
        return $this;
    }

    public function setValidationGroup(array $validationGroup): static
    {
        foreach ($validationGroup as $key => $value) {
            if (! \is_string($key)) {
                continue;
            }
            
            if (! $this->hasProperty($key)) {
                continue;
            }

            $this->validationGroup[$key] = $value;
        }

        return $this;
    }

    public function toArray(): array
    {
        $values = [];
        foreach ($this->getProperties() as $property) {
            $value = $property->getValue();
            $values[$property->getName()] = $value instanceof self
                ? $value->toArray()
                : $value;
        }

        return $values;
    }

    private function getPropertiesInputFilter(ValidatorPluginManager $validatorPluginManager): InputFilter
    {
        $inputFilter = new InputFilter();
        $filterPluginManager = new FilterPluginManager(
            new ServiceManager(),
            ['dependencies' => Config::get('filters')]
        );
        foreach ($this->getProperties() as $property) {
            if ($property->isAutoIncrement()) {
                continue;
            }

            // dynamically create an input for the property
            $input = new Input($property->getName());

            // add property filters and validators
            foreach ($property->getFilters() as $filter => $options) {
                $input->getFilterChain()->attach(
                    $filterPluginManager->get($filter, $options),
                    $options['priority'] ?? FilterChain::DEFAULT_PRIORITY
                );
            }

            foreach ($property->getValidators() as $validator => $options) {
                $input->getValidatorChain()->attach(
                    $validatorPluginManager->get($validator, $options),
                    $options['break_chain_on_failure'] ?? FALSE,
                    $options['priority'] ?? ValidatorChain::DEFAULT_PRIORITY
                );
            }

            // set input options
            $this->isRelationProperty($property->getIdentifier())
                ? $input->setAllowEmpty(FALSE)->setRequired(TRUE)
                : $input->setRequired($property->getNotNull());

            // append the input to theinput filter
            $inputFilter->add($input);
        }

        // set a validation group, if any
        if (! empty($this->validationGroup)) {
            $inputFilter->setValidationGroup($this->validationGroup);
        }

        return $inputFilter;
    }
}
