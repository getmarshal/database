<?php

declare(strict_types=1);

namespace Marshal\Database\Query;

use Marshal\Database\Event\Content\ContentCreatedEvent;
use Marshal\Database\Event\Content\CreateQueryEvent;
use Marshal\Database\QueryBuilder;
use Marshal\Database\Hydrator\ItemInputHydrator;
use Marshal\Database\Query\Exception\DatabaseQueryException;
use Marshal\Database\Query\Exception\InvalidInputException;
use Marshal\Database\Schema\Content;
use Marshal\Database\Schema\ContentManager;

final class Create extends AbstractQuery
{
    use Validate;

    public function __construct(private Content $content)
    {
    }

    public function execute(): object
    {
        $query = $this->prepare();

        $query->getEventDispatcher()?->dispatch(new CreateQueryEvent($query, $this->content));

        // validate the type
        if (! $this->isValid($this->content)) {
            throw new InvalidInputException($this->getValidationMessages());
        }

        // execute the query
        try {
            $result = $query->executeStatement();
        } catch (\Throwable $e) {
            throw new DatabaseQueryException($e, $query);
        }

        if (\intval($result) > 0) {
            // update the autoincrement property
            $this->content->getAutoIncrement()->setValue(
                \intval($query->lastInsertId())
            );

            $query->getEventDispatcher()?->dispatch(new ContentCreatedEvent($this->content));
        }

        return $this->content;
    }

    public static function fromArray(string $target, array $values): static
    {
        $content = ContentManager::get($target);

        $hydrator = new ItemInputHydrator();
        $hydrator->hydrate($content, $values);

        return new self($content);
    }

    public static function target(Content $target): object
    {
        $create = new self($target);
        return $create->execute();
    }

    protected function prepare(): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder($this->content->getContentConfig()->getDatabase());
        $queryBuilder->insert($this->content->getContentConfig()->getTable());
        foreach ($this->content->getProperties() as $property) {
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
