<?php

declare(strict_types=1);

namespace Marshal\Database\Handler;

trait ContentHandlerTrait
{
    private function getSelectedSchemaType(string $type, array $schemaConfig): ?string
    {
        foreach ($schemaConfig['types'] as $identifier => $config) {
            if (! isset($config['tag'])) {
                continue;
            }

            if ($config['tag'] !== $type) {
                continue;
            }

            return $identifier;
        }

        return null;
    }

    private function getSchemaConfig(string $schema, array $databaseConfig): array
    {
        foreach ($databaseConfig as $dbConfig) {
            if (! isset($dbConfig['tag'])) {
                continue;
            }

            if ($dbConfig['tag'] !== $schema) {
                continue;
            }

            return $dbConfig;
        }

        return [];
    }

    private function getSchemaName(string $schema, array $databaseConfig): ?string
    {
        foreach ($databaseConfig as $dbName => $dbConfig) {
            if (! isset($dbConfig['tag'])) {
                continue;
            }

            if ($dbConfig['tag'] !== $schema) {
                continue;
            }

            return $dbName;
        }

        return null;
    }
}
