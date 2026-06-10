<?php

declare(strict_types=1);

namespace Marshal\Database\Query;

use Laminas\Filter\FilterChain;
use Laminas\Filter\FilterPluginManager;
use Laminas\InputFilter\Input;
use Laminas\InputFilter\InputFilter;
use Laminas\InputFilter\InputFilterInterface;
use Laminas\ServiceManager\ServiceManager;
use Laminas\Validator\ValidatorChain;
use Laminas\Validator\ValidatorPluginManager;
use Marshal\Database\Schema\Content;
use Marshal\Utils\Config;

trait Validate
{
    private array $validationGroup = [];
    private array $validationMessages = [];

    public function getValidationMessages(): array
    {
        return $this->validationMessages;
    }

    public function isValid(Content $content): bool
    {
        // create our validator plugin manager
        $validatorManager = new ValidatorPluginManager(
            new ServiceManager(),
            ['dependencies' => Config::get('validators')]
        );

        // validate individual properties
        $inputFilter = $this->getPropertiesInputFilter($content, $validatorManager);
        if (! $inputFilter->setData($content->toArray())->isValid()) {
            foreach ($inputFilter->getMessages() as $key => $message) {
                $this->validationMessages[$key] = $message;
            }
        }

        // chain content level validators
        $validators = $content->getContentConfig()->getValidators();
        if (! empty($validators)) {
            $chain = $validatorManager->get(ValidatorChain::class);
            \assert($chain instanceof ValidatorChain);
            foreach ($validators as $validator => $options) {
                $options['__operation'] = static::class;
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

    private function setValidationGroup(array $validationGroup): void
    {
        foreach ($validationGroup as $key) {
            if (! \is_string($key)) {
                continue;
            }

            if (! $this->content->hasProperty($key)) {
                continue;
            }

            $this->validationGroup[] = $key;
        }
    }

    private function getPropertiesInputFilter(Content $content, ValidatorPluginManager $validatorPluginManager): InputFilterInterface
    {
        $inputFilter = new InputFilter();
        $filterPluginManager = new FilterPluginManager(
            new ServiceManager(),
            ['dependencies' => Config::get('filters')]
        );

        foreach ($content->getProperties() as $property) {
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
            $content->isRelationProperty($property->getIdentifier())
                ? $input->setAllowEmpty(FALSE)->setRequired(TRUE)
                : $input->setRequired($property->getNotNull())->setAllowEmpty(TRUE);

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
