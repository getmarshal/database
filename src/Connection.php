<?php

declare(strict_types=1);

namespace Marshal\Database;

use Doctrine\DBAL\Connection as DBALConnection;
use Psr\EventDispatcher\EventDispatcherInterface;

class Connection extends DBALConnection
{
    private ?EventDispatcherInterface $eventDispatcher = null;

    public function createQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this);
    }

    public function getEventDispatcher(): ?EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): void
    {
        $this->eventDispatcher = $eventDispatcher;
    }
}
