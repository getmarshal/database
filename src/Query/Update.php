<?php

declare(strict_types=1);

namespace Marshal\Database\Query;

use Marshal\Database\Hydrator\TypeInputHydrator;
use Marshal\Database\Query;
use Marshal\Database\Query\Trait\ValuesTrait;
use Marshal\Database\Query\Trait\WhereTrait;
use Marshal\Database\QueryBuilder;
use Marshal\Database\Schema\Type;
use Marshal\Utils\Logger\LoggerManager;

final class Update extends Query
{
    use ValuesTrait;
    use WhereTrait;

    public function __construct(protected Type $type)
    {
    }

    public function execute(): Type
    {
        if (empty($this->values)) {
            return $this->type;
        }

        // prepare the query
        $query = $this->prepare();

        // validate properties being updated
        $this->type->setValidationGroup($this->values);
        if (! $this->type->isValid(self::class)) {
            throw new Exception\InvalidInputException($this->type->getValidationMessages());
        }
        
        $result = $query->executeStatement();
        if (! \is_numeric($result) || \intval($result) == 0) {
            LoggerManager::get()->info("No updates by: ", [
                'sql' => $query->getSQL(),
                'parameters' => $query->getParameters(),
            ]);
        }

        return $this->type;
    }

    protected function prepare(): QueryBuilder
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->update($this->type->getTable());
        $this->applyWhereExpressions($queryBuilder);

        // hydrate the type
        $hydrator = new TypeInputHydrator();
        $hydrator->hydrate($this->type, $this->values);

        foreach ($this->values as $name => $value) {
            if (! $this->type->hasProperty($name)) {
                continue;
            }

            $property = $this->type->getProperty($name);
            $queryBuilder->set(
                $property->getName(),
                $queryBuilder->createNamedParameter(
                    $property->getDatabaseType()->convertToDatabaseValue($value, $queryBuilder->getDatabasePlatform()),
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