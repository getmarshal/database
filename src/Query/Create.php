<?php

declare(strict_types=1);

namespace Marshal\Database\Query;

use Marshal\Database\Query;
use Marshal\Database\Query\Exception\DatabaseQueryException;
use Marshal\Database\Query\Exception\InvalidInputException;
use Marshal\Database\Query\Hydrator\ItemInputHydrator;
use Marshal\Database\QueryBuilder;
use Marshal\Database\Schema\Type;
use Marshal\Database\Schema\TypeManager;

class Create extends Query
{
    public function __construct(private Type $type)
    {
    }

    public function execute(): object
    {
        $query = $this->prepare();

        // validate the type
        if (! $this->type->isValid(self::class)) {
            throw new InvalidInputException($this->type->getValidationMessages());
        }

        // execute the query
        try {
            $query->executeStatement();
        } catch (\Throwable $e) {
            throw new DatabaseQueryException($e, $query);
        }

        // update the autoincrement property
        $this->type->getAutoIncrement()->setValue(
            \intval($query->lastInsertId())
        );
        
        return $this->type;
    }

    public static function fromArray(string $target, array $values): static
    {
        $type = TypeManager::get($target);

        $hydrator = new ItemInputHydrator();
        $hydrator->hydrate($type, $values);
        
        return new self($type);
    }

    public static function fromObject(object $target): static
    {
        if (! $target instanceof Type) {
            throw new \InvalidArgumentException(\sprintf(
                "Invalid create object. Expected %s, given %s instead",
                Type::class, \get_debug_type($target)
            ));
        }

        return new self($target);
    }

    protected function prepare(): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder($this->type->getDatabase());
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

            $queryBuilder->setValue(
                $property->getName(),
                $queryBuilder->createNamedParameter(
                    $property->convertToDatabaseValue($queryBuilder->getDatabasePlatform()),
                    $property->getDatabaseType()->getBindingType()
                )
            );
        }

        return $queryBuilder;
    }
}