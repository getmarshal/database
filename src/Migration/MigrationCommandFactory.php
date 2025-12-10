<?php

declare(strict_types= 1);

namespace Marshal\Utils\Database\Migration;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;

final class MigrationCommandFactory
{
    public function __invoke(ContainerInterface $container, $requestedName): object
    {
        $commands = $container->get('config')['commands'] ?? [];

        // check for requested command in config
        $commandName = \array_search($requestedName, $commands, true);
        if (! \is_string($commandName)) {
            throw new \InvalidArgumentException(\sprintf(
                "Command %s not found in configuration",
                $requestedName
            ));
        }

        try {
            $command = new $requestedName($container, $commandName);
            if (! $command instanceof Command) {
                throw new \InvalidArgumentException(\sprintf(
                    "Expected command instance to extend %s, given %s instead",
                    Command::class,
                    \get_debug_type($command)
                ));
            }

        } catch (\Throwable $e) {
            throw new \InvalidArgumentException($e->getMessage());
        }

        return $command;
    }
}
