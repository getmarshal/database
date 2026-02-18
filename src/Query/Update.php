<?php

declare(strict_types=1);

namespace Marshal\Database\Query;

use Marshal\Database\Query;
use Marshal\Database\Query\Exception\InvalidInputException;
use Marshal\Database\Query\Hydrator\ItemInputHydrator;
use Marshal\Database\QueryBuilder;
use Marshal\Database\Query\Trait\ValuesTrait;
use Marshal\Database\Query\Trait\WhereTrait;
use Marshal\Database\Schema\Type;
use Marshal\Utils\Logger\LoggerManager;

class Update extends Query
{
    use ValuesTrait;
    use WhereTrait;

    public function __construct(private Type $type)
    {
    }

    public static function target(object $target): static
    {
        if (! $target instanceof Type) {
            throw new \InvalidArgumentException(\sprintf(
                "Invalid update query. Expected object of type %s, given %s instead",
                Type::class, \get_debug_type($target)
            ));
        }

        return new self($target);
    }

    public function execute(): int|string
    {
        // prepare the query
        $query = $this->prepare();

        // validate properties being updated
        $this->type->setValidationGroup($this->values);
        if (! $this->type->isValid(self::class)) {
            throw new InvalidInputException($this->type->getValidationMessages());
        }
        
        return $query->executeStatement();
    }

    protected function prepare(): QueryBuilder
    {
        if (empty($this->values)) {
            throw new \RuntimeException("No values to update");
        }

        $queryBuilder = $this->createQueryBuilder($this->type->getDatabase());
        $queryBuilder->update($this->type->getTable());
        $this->applyWhereExpressions($queryBuilder, $this->type);

        // hydrate the type
        $hydrator = new ItemInputHydrator();
        $hydrator->hydrate($this->type, $this->values);

        foreach ($this->values as $name => $value) {
            if (! $this->type->hasProperty($name)) {
                LoggerManager::get()->warning(\sprintf(
                    "Property %s not found on update type %s",
                    $name, $this->type->getIdentifier()
                ));
                continue;
            }

            $property = $this->type->getProperty($name);
            $queryBuilder->set(
                $property->getName(),
                $queryBuilder->createNamedParameter(
                    $property->convertToDatabaseValue($queryBuilder->getDatabasePlatform()),
                    $property->getDatabaseType()->getBindingType()
                )
            );
        }

        $queryBuilder->andWhere($queryBuilder->expr()->eq(
            $this->type->getAutoIncrement()->getName(),
            $queryBuilder->createNamedParameter(
                $this->type->getAutoIncrement()->getValue()
            )
        ));

        return $queryBuilder;
    }
}