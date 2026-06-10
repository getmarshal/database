<?php

declare(strict_types=1);

namespace Marshal\Database\Handler;

use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Marshal\Utils\Helper\ServerRequestHelperTrait;

final class ContentSchemaHandler implements RequestHandlerInterface
{
    use ContentHandlerTrait;
    use ServerRequestHelperTrait;

    public const string ROUTE_CONTENT_SCHEMA = "marshal::content-schema";

    public function __construct(private array $databaseConfig, private array $schemaConfig)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $platform = $this->getRequestPlatform($request);
        $schema = $request->getAttribute('schema');

        $selectedDbName = $this->getSchemaName($schema, $this->databaseConfig);
        $selectedDbConfig = $this->getSchemaConfig($schema, $this->databaseConfig);

        if (null === $selectedDbName || empty($selectedDbConfig)) {
            return $platform->notFoundResponse($request);
        }

        $data = [];
        foreach ($this->schemaConfig['types'] ?? [] as $type) {
            if (! isset($type['database']) || ! isset($type['tag'])) {
                continue;
            }

            if ($type['database'] !== $selectedDbName) {
                if (! isset($selectedDbConfig['tag']) || $type['database'] !== $selectedDbConfig['tag']) {
                    continue;
                }
            }

            $data[$type['tag']] = [
                'name' => $type['name'],
                'description' => $type['description'],
            ];
        }

        return $platform->formatResponse($request, [
            'schema' => $selectedDbConfig,
            'data' => $data,
        ], "marshal::content-schema");
    }
}
