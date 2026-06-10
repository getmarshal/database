<?php

declare(strict_types=1);

namespace Marshal\Database\Query;

use Marshal\Database\Event\Content\ContentUpdatedEvent;
use Marshal\Database\Event\Content\UpdateQueryEvent;
use Marshal\Database\Query\Modifier\Set;
use Marshal\Database\Query\Modifier\Where;
use Marshal\Database\QueryBuilder;
use Marshal\Database\Hydrator\ItemInputHydrator;
use Marshal\Database\Schema\Content;
use Marshal\Utils\Logger\LoggerManager;

class Update extends AbstractQuery
{
    use Set;
    use Validate;
    use Where;

    public function __construct(private Content $content)
    {
    }

    public function execute(): int|string
    {
        // prepare the query
        $query = $this->prepare();

        // validate properties being updated
        $this->setValidationGroup(\array_keys($this->values));
        if (! $this->isValid($this->content)) {
            throw new Exception\InvalidInputException($this->getValidationMessages());
        }

        $query->getEventDispatcher()?->dispatch(new UpdateQueryEvent($query, $this->content, $this->values));

        try {
            $result = $query->executeStatement();
        } catch (\Throwable $e) {
            throw new Exception\UpdateQueryException($query, $this->content, $e);
        }

        $query->getEventDispatcher()?->dispatch(new ContentUpdatedEvent($this->content, $this->values));

        return $result;
    }

    public static function target(Content $target): static
    {
        return new self($target);
    }

    protected function prepare(): QueryBuilder
    {
        if (empty($this->values)) {
            throw new \RuntimeException("No values to update");
        }

        $queryBuilder = $this->createQueryBuilder($this->content->getContentConfig()->getDatabase());
        $queryBuilder->update($this->content->getContentConfig()->getTable());
        $this->applyWhereExpressions($queryBuilder, $this->content);

        // hydrate the type
        $hydrator = new ItemInputHydrator();
        $hydrator->hydrate($this->content, $this->values);

        foreach (\array_keys($this->values) as $name) {
            if (! $this->content->hasProperty($name)) {
                LoggerManager::get()->warning(\sprintf(
                    "Property %s not found on update type %s",
                    $name, $this->content->getSchemaIdentifier()
                ));
                continue;
            }

            $property = $this->content->getProperty($name);
            $queryBuilder->set(
                $property->getName(),
                $queryBuilder->createNamedParameter(
                    $property->convertToDatabaseValue($queryBuilder->getDatabasePlatform()),
                    $property->getDatabaseType()->getBindingType()
                )
            );
        }

        $queryBuilder->andWhere($queryBuilder->expr()->eq(
            $this->content->getAutoIncrement()->getName(),
            $queryBuilder->createNamedParameter(
                $this->content->getAutoIncrement()->getValue()
            )
        ));

        return $queryBuilder;
    }
}