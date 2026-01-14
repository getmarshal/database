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

class Type
{
    private array $relations = [];
    private array $validationGroup = [];
    private array $validationMessages = [];
    
    /**
     * @var array<string, Property>
     */
    private array $properties = [];

    final public function __construct(
        private string $identifier,
        private string $database,
        private string $table,
        private array $config
    ) {
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

    public function getCollectionTemplate(): string
    {
        return $this->config['templates']['collection'];
    }

    public function getContentTemplate(): string
    {
        return $this->config['templates']['content'];
    }

    public function getDatabase(): string
    {
        return $this->database;
    }

    public function getDescription(): string
    {
        return $this->config["description"];
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getName(): string
    {
        return $this->config["name"];
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
            \sprintf("Property %s does not exist in type: %s", $identifier, $this->getName())
        );
    }

    public function getRelation(string $identifier): TypeRelation
    {
        if (isset($this->relations[$identifier])) {
            return $this->relations[$identifier];
        }

        foreach ($this->getRelations() as $relation) {
            if (
                $identifier === $relation->getLocalProperty()->getName()
                || $identifier === $relation->getLocalProperty()->getIdentifier()
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

    public function getRoutePrefix(): string
    {
        return $this->config['routing']['route_prefix'] ?? '';
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getValidationMessages(): array
    {
        return $this->validationMessages;
    }

    public function getValidators(): array
    {
        return $this->config['validators'] ?? [];
    }

    public function hasCollectionTemplate(): bool
    {
        return isset($this->config['templates']['collection']);
    }

    public function hasContentTemplate(): bool
    {
        return isset($this->config['templates']['content']);
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

    public function hasRoutePrefix(): bool
    {
        return isset($this->config['routing']['route_prefix']);
    }

    public function isEmpty(): bool
    {
        // check for a non-null autoincrement property
        return null === $this->getAutoIncrement()->getValue();
    }

    public function isRelationProperty(string $identifier): bool
    {
        if (! $this->hasProperty($identifier)) {
            return FALSE;
        }

        foreach ($this->getRelations() as $relation) {
            if ($relation->getLocalProperty()->getIdentifier() === $identifier
                || $relation->getLocalProperty()->getName() === $identifier
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
        $chain = $validatorManager->get(ValidatorChain::class);
        \assert($chain instanceof ValidatorChain);
        foreach ($this->getValidators() as $validator => $options) {
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
        
        return empty($this->validationMessages);
    }

    public function removeProperty(string $identifier): static
    {
        foreach ($this->getProperties() as $name => $property) {
            if ($identifier !== $property->getIdentifier()) {
                continue;
            }

            unset($this->properties[$name]);
        }

        return $this;
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
                : $input->setAllowEmpty(TRUE)->setRequired($property->getNotNull());

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
