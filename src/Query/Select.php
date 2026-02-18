<?php

declare(strict_types=1);

namespace Marshal\Database\Query;

use Marshal\Database\Query;
use Marshal\Database\Query\Exception\DatabaseQueryException;
use Marshal\Database\Query\Hydrator\DatabaseResultHydrator;
use Marshal\Database\Query\Trait\GroupByTrait;
use Marshal\Database\Query\Trait\HavingTrait;
use Marshal\Database\Query\Trait\OrderByTrait;
use Marshal\Database\Query\Trait\PropertiesTrait;
use Marshal\Database\Query\Trait\RelationsTrait;
use Marshal\Database\Query\Trait\WhereTrait;
use Marshal\Database\QueryBuilder;
use Marshal\Database\Schema\Type;
use Marshal\Database\Schema\TypeManager;

class Select extends Query
{
    use GroupByTrait;
    use HavingTrait;
    use OrderByTrait;
    use PropertiesTrait;
    use RelationsTrait;
    use WhereTrait;

    private ?int $limit = null;
    private int $offset = 0;

    public function __construct(private Type $type)
    {
    }

    public function fetch(): object
    {
        $query = $this->prepare();
        $result = $this->fetchArrayResult($query);

        if (! empty($result)) {
            $hydrator = new DatabaseResultHydrator();
            $hydrator->hydrate($this->type, $result, $query->getDatabasePlatform());
        }

        return $this->type;
    }

    public function fetchAllAssociative(): array
    {
        $query = $this->prepare();
        try {
            $result = $query->setFirstResult($this->offset)
                ->setMaxResults($this->limit)
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            throw new DatabaseQueryException($e, $query);
        }

        return $result;
    }

    public function fetchAssociative(): array
    {
        return $this->fetchArrayResult($this->prepare());
    }

    public static function from(string $from): static
    {
        return new self(TypeManager::get($from));
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offset = $offset;
        return $this;
    }

    protected function fetchArrayResult(QueryBuilder $query): array
    {
        try {
            $result = $query->setMaxResults(1)
                ->executeQuery()
                ->fetchAssociative();
        } catch (\Throwable $e) {
            throw new DatabaseQueryException($e, $query);
        }

        return \is_array($result) ? $result : [];
    }

    protected function prepare(): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder($this->type->getDatabase());
        $queryBuilder->from($this->type->getTable(), $this->type->getTable());

        $this->applyDistincts($queryBuilder, $this->type);
        $this->applyProperties($queryBuilder, $this->type);
        $this->applyRelations($queryBuilder, $this->type);
        $this->applyWhereExpressions($queryBuilder, $this->type);
        $this->applyGroupByExpressions($queryBuilder);
        $this->applyHavingExpressions($queryBuilder);
        $this->applyOrderByExpressions($queryBuilder);

        return $queryBuilder;
    }
}
