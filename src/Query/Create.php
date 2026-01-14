<?php

declare(strict_types=1);

namespace Marshal\Database\Query;

use Marshal\Database\Hydrator\TypeInputHydrator;
use Marshal\Database\Query;
use Marshal\Database\QueryBuilder;
use Marshal\Database\Schema\Type;
use Marshal\Utils\Logger\LoggerManager;

final class Create extends Query
{
    public function __construct(protected Type $type)
    {
    }

    public function execute(): Type
    {
        $query = $this->prepare();

        // validate the type
        if (! $this->type->isValid(self::class)) {
            throw new Exception\InvalidInputException($this->type->getValidationMessages());
        }

        // execute the query
        try {
            $result = $query->executeStatement();
        } catch (\Throwable $e) {
            throw new Exception\DatabaseQueryException($e);
        }

        // check the result
        if (! \is_numeric($result) || \intval($result) == 0) {
            LoggerManager::get()->error("Content not created", [
                'sql' => $query->getSQL(),
                'parameters' => $query->getParameters(),
            ]);
        }
        
        return $this->type;
    }

    public function fromInput(array $values): static
    {
        $hydrator = new TypeInputHydrator();
        $hydrator->hydrate($this->type, $values);
        
        return $this;
    }

    public function fromType(Type $type): static
    {
        $this->type = $type;
        return $this;
    }

    protected function prepare(): QueryBuilder
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->insert($this->type->getTable());
        foreach ($this->type->getProperties() as $property) {
            if ($property->isAutoIncrement()) {
                continue;
            }

            if (true === $property->getNotNull() && null === $property->getValue()) {
                if (\is_callable($property->getDefaultValue())) {
                    $property->setValue(\call_user_func($property->getDefaultValue()));
                } else {
                    $property->setValue($property->getDefaultValue());
                }
            }

            $value = $property->getValue();
            if ($value instanceof Type) {
                $value = $value->getAutoIncrement()->getValue();
            }

            $queryBuilder->setValue(
                $property->getName(),
                $queryBuilder->createNamedParameter(
                    $property->getDatabaseType()->convertToDatabaseValue($value, $queryBuilder->getDatabasePlatform()),
                    $property->getDatabaseType()->getBindingType()
                )
            );
        }

        return $queryBuilder;
    }
}