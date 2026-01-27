<?php

declare(strict_types=1);

namespace Marshal\Database\Query;

use loophp\collection\Collection;
use Marshal\Database\Hydrator\DatabaseResultHydrator;
use Marshal\Database\Query;
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
    private bool $toArray = false;

    public function __construct(string $identifier)
    {
        $this->type = TypeManager::get($identifier);
    }

    public function count(): int
    {
        return $this->fetchAllLazy()->count();
    }

    public function fetch(): array
    {
        return $this->fetchArrayResult($this->prepare());
    }

    public function fetchAll(): array
    {
        $query = $this->prepare();
        try {
            $result = $query->setFirstResult($this->offset)
                ->setMaxResults($this->limit)
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            throw new Exception\DatabaseQueryException($e, $query);
        }

        return $result;
    }

    public function fetchAllLazy(): Collection
    {
        $query = $this->prepare();
        try {
            $iterable = $query->setFirstResult($this->offset)
                ->setMaxResults($this->limit)
                ->executeQuery()
                ->iterateAssociative();
        } catch (\Throwable $e) {
            throw new Exception\DatabaseQueryException($e, $query);
        }

        $type = $this->type;
        $hydrator = new DatabaseResultHydrator;
        $platform = $query->getDatabasePlatform();
        $toArray = $this->toArray;

        return Collection::fromCallable(static function () use ($iterable, $toArray, $type, $platform, $hydrator): \Generator {
            foreach ($iterable as $row) {
                $hydrator->hydrate($type, $row, $platform);
                yield $toArray ? $type->toArray() : $type;
            }
        });
    }

    public function fetchType(): Type
    {
        $query = $this->prepare();
        $result = $this->fetchArrayResult($query);

        if (! empty($result)) {
            $hydrator = new DatabaseResultHydrator();
            $hydrator->hydrate($this->type, $result, $query->getDatabasePlatform());
        }

        return $this->type;
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

    public function toArray(): static
    {
        $this->toArray = true;
        return $this;
    }

    protected function prepare(): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder->from($this->type->getTable(), $this->type->getTable());

        $this->applyDistincts($queryBuilder, $this->type);
        $this->applyProperties($queryBuilder, $this->type);
        $this->applyRelations($queryBuilder, $this->type->getRelations());
        $this->applyWhereExpressions($queryBuilder, $this->type);
        $this->applyGroupByExpressions($queryBuilder);
        $this->applyHavingExpressions($queryBuilder);
        $this->applyOrderByExpressions($queryBuilder);

        return $queryBuilder;
    }

    private function fetchArrayResult(QueryBuilder $query): array
    {
        try {
            $result = $query->setMaxResults(1)
                ->executeQuery()
                ->fetchAssociative();
        } catch (\Throwable $e) {
            throw new Exception\DatabaseQueryException($e, $query);
        }

        return \is_array($result) ? $result : [];
    }
}
