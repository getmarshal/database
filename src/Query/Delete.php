<?php

declare(strict_types=1);

namespace Marshal\Database\Query;

use Marshal\Database\Event\Content\ContentDeletedEvent;
use Marshal\Database\Event\Content\DeleteQueryEvent;
use Marshal\Database\Query\Modifier\Where;
use Marshal\Database\QueryBuilder;
use Marshal\Database\Schema\Content;
use Marshal\Database\Schema\ContentManager;

final class Delete extends AbstractQuery
{
    use Where;

    public function __construct(private Content $content)
    {
    }

    public function execute(): int|string
    {
        $query = $this->prepare();
        $query->getEventDispatcher()?->dispatch(new DeleteQueryEvent($query));
        $result = $query->executeStatement();
        if (\intval($result) > 0) {
            $query->getEventDispatcher()?->dispatch(new ContentDeletedEvent($this->content));
        }
        return $result;
    }

    public static function from(string $target): static
    {
        return new self(ContentManager::get($target));
    }

    public static function target(Content $target): int|string
    {
        if (! $target->hasProperty(Content::ID)) {
            throw new \InvalidArgumentException(\sprintf(
                "ContentDelete target has no %s property",
                Content::class
            ));
        }

        if (null === $target->getAutoIncrement()->getValue()) {
            throw new \InvalidArgumentException(\sprintf(
                "ContentDelete target has no primary key"
            ));
        }

        $delete = new self($target);
        return $delete
            ->where(Content::ID, $target->getAutoIncrement()->getValue())
            ->execute();
    }

    protected function prepare(): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder($this->content->getContentConfig()->getDatabase());
        $queryBuilder->delete($this->content->getContentConfig()->getTable());
        $this->applyWhereExpressions($queryBuilder, $this->content);

        return $queryBuilder;
    }
}