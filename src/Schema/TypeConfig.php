<?php

declare(strict_types=1);

namespace Marshal\Database\Schema;

final class TypeConfig
{
    public function __construct(private readonly array $config)
    {
    }

    public function getCollectionTemplate(): string
    {
        return $this->config['templates']['collection'];
    }

    public function getContentTemplate(): string
    {
        return $this->config['templates']['content'];
    }

    public function getDescription(): string
    {
        return $this->config["description"];
    }

    public function getName(): string
    {
        return $this->config["name"];
    }

    public function getRoutePrefix(): string
    {
        return $this->config['routing']['route_prefix'] ?? '';
    }

    public function hasCollectionTemplate(): bool
    {
        return isset($this->config['templates']['collection']);
    }

    public function hasContentTemplate(): bool
    {
        return isset($this->config['templates']['content']);
    }

    public function hasRoutePrefix(): bool
    {
        return isset($this->config['routing']['route_prefix']);
    }
}
