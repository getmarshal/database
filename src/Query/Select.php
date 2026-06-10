<?php

declare(strict_types=1);

namespace Marshal\Database\Query;

use Marshal\Database\Query\Modifier\GroupBy;
use Marshal\Database\Query\Modifier\Having;
use Marshal\Database\Query\Modifier\OrderBy;
use Marshal\Database\Query\Modifier\Properties;
use Marshal\Database\Query\Modifier\Relations;
use Marshal\Database\Query\Modifier\Where;
use Marshal\Database\QueryBuilder;
use Marshal\Database\Schema\Content;
use Marshal\Database\Schema\ContentManager;
use loophp\collection\Collection;
use Marshal\Database\Hydrator\ContentResultHydrator;

final class Select extends AbstractQuery
{
    use GroupBy;
    use Having;
    use OrderBy;
    use Properties;
    use Relations;
    use Where;

    private ?int $limit = null;
    private int $offset = 0;

    public function __construct(private Content $content)
    {
    }

    public function count(): int
    {
        return $this->fetchAllLazy()->count();
    }

    public function fetch(): object
    {
        $this->limit(1);
        $query = $this->prepare();
        $result = $this->fetchArrayResult($query);

        if (! empty($result)) {
            $hydrator = new ContentResultHydrator();
            $hydrator->hydrate($this->content, $result, $query->getDatabasePlatform());
        }

        return $this->content;
    }

    public function fetchAllAssociative(): array
    {
        $query = $this->prepare();
        try {
            $result = $query
                ->executeQuery()
                ->fetchAllAssociative();
        } catch (\Throwable $e) {
            throw new Exception\DatabaseQueryException($e, $query);
        }

        return $result;
    }

    public function fetchAllLazy(bool $toArray = false): Collection
    {
        $query = $this->prepare();
        try {
            $iterable = $query
                ->executeQuery()
                ->iterateAssociative();
        } catch (\Throwable $e) {
            throw new Exception\DatabaseQueryException($e, $query);
        }

        $content = $this->content;
        $hydrator = new ContentResultHydrator();
        $platform = $query->getDatabasePlatform();

        return Collection::fromCallable(static function () use ($iterable, $toArray, $content, $platform, $hydrator): \Generator {
            foreach ($iterable as $row) {
                $hydrator->hydrate($content, $row, $platform);
                yield $toArray ? $content->toArray() : $content;
            }
        });
    }

    public function fetchAssociative(): array
    {
        $this->limit(1);
        return $this->fetchArrayResult($this->prepare());
    }

    public static function from(string $from): static
    {
        return new self(ContentManager::get($from));
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
            $result = $query
                ->executeQuery()
                ->fetchAssociative();
        } catch (\Throwable $e) {
            throw new Exception\DatabaseQueryException($e, $query);
        }

        return \is_array($result) ? $result : [];
    }

    protected function getLimit(): ?int
    {
        return $this->limit;
    }

    protected function getOffset(): int
    {
        return $this->offset;
    }

    protected function prepare(): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder($this->content->getContentConfig()->getDatabase());
        $queryBuilder->from($this->content->getContentConfig()->getTable(), $this->content->getContentConfig()->getTable());

        $this->applyDistincts($queryBuilder, $this->content);
        $this->applyProperties($queryBuilder, $this->content);
        $this->applyRelations($queryBuilder, $this->content);
        $this->applyWhereExpressions($queryBuilder, $this->content);
        $this->applyGroupByExpressions($queryBuilder);
        $this->applyHavingExpressions($queryBuilder);
        $this->applyOrderByExpressions($queryBuilder);

        $queryBuilder->setMaxResults($this->limit)->setFirstResult($this->offset);

        return $queryBuilder;
    }
}
