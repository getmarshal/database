<?php

declare(strict_types= 1);

namespace Marshal\Utils\Database\Validator;

use Psr\Container\ContainerInterface;

final class TypeConfigValidatorFactory
{
    public function __invoke(ContainerInterface $container) : TypeConfigValidator
    {
        $config = $container->get("config")["schema"]["types"] ?? [];
        return new TypeConfigValidator($config);
    }
}
