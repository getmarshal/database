<?php

declare(strict_types= 1);

namespace Marshal\Utils\Database\Validator;

use Psr\Container\ContainerInterface;

final class PropertyConfigValidatorFactory
{
    public function __invoke(ContainerInterface $container) : PropertyConfigValidator
    {
        $config = $container->get("config")["schema"]["properties"] ?? [];
        return new PropertyConfigValidator($config);
    }
}
