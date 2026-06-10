<?php

declare(strict_types=1);

namespace Marshal\Database\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Marshal\Utils\Helper\ServerRequestHelperTrait;

final class ContentDashboard implements RequestHandlerInterface
{
    use ServerRequestHelperTrait;

    public const string ROUTE_DASHBOARD = "marshal::content-dashboard";

    public function __construct(private array $databasesConfig)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $platform = $this->getRequestPlatform($request);

        $data = [];
        foreach ($this->databasesConfig as $database) {
            if (isset($database['system']) && true === $database['system']) {
                continue;
            }

            if (! isset($database['tag'])) {
                continue;
            }

            $data[$database['tag']] = [
                'name' => $database['label'],
            ];
        }

        return $platform->formatResponse($request, [
            'data' => $data,
        ], "marshal::content-dashboard");
    }
}
