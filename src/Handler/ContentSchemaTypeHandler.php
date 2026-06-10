<?php

declare(strict_types=1);

namespace Marshal\Database\Handler;

use Marshal\Database\Query\Select;
use Marshal\Database\Schema\Content;
use Marshal\Database\Schema\ContentForm;
use Marshal\Platform\PlatformInterface;
use Marshal\Utils\Helper\ServerRequestHelperTrait;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Marshal\Database\Schema\ContentManager;

final class ContentSchemaTypeHandler implements RequestHandlerInterface
{
    use ContentHandlerTrait;
    use ServerRequestHelperTrait;

    public const string ROUTE_CONTENT_SCHEMA_TYPE = "marshal::content-schema-type";
    public const string TEMPLATE_CONTENT_SCHEMA_TYPE_INDEX = "marshal::content-schema-type-index-template";
    public const string TEMPLATE_CONTENT_SCHEMA_TYPE = "marshal::content-schema-type-template";

    public function __construct(
        private ContainerInterface $container,
        private array $databaseConfig,
        private array $schemaConfig
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $platform = $this->getRequestPlatform($request);
        $schema = $request->getAttribute('schema');
        $type = $request->getAttribute('type');

        $selectedDbName = $this->getSchemaName($schema, $this->databaseConfig);
        $selectedDbConfig = $this->getSchemaConfig($schema, $this->databaseConfig);
        $selectedSchemaType = $this->getSelectedSchemaType($type, $this->schemaConfig);

        if (null === $selectedDbName || empty($selectedDbConfig) || empty($selectedSchemaType)) {
            return $platform->notFoundResponse($request);
        }

        $content = ContentManager::get($selectedSchemaType);
        if (null === $content->getContentConfig()->getHandler()) {
            return $this->handleContentView($request, $platform, $content);
        }

        $handler = $this->container->get($content->getContentConfig()->getHandler());
        \assert($handler instanceof RequestHandlerInterface);

        return $handler->handle($request);
    }

    private function handleContentView(ServerRequestInterface $request, PlatformInterface $platform, Content $content): ResponseInterface
    {
        $params = $request->getQueryParams();
        if (empty($params) || ! isset($params['tag'])) {
            return $this->handleContentViewIndex($request, $platform, $content);
        }

        // hydrate the content
        $content = $this->hydrateContent($request, $content);

        // check whether content was hydrated, i.e found
        if ($content->isEmpty()) {
            return $platform->notFoundResponse($request);
        }

        // view an edit page
        if (isset($params['action']) && $params['action'] === "edit" && 'GET' === \strtoupper($request->getMethod())) {
            return $this->handleContentEditView($request, $platform, $content);
        }

        // updating content
        if (isset($params['action']) && $params['action'] === "edit" && 'POST' === \strtoupper($request->getMethod())) {
            return $this->handleContentUpdate($request, $platform, $content);
        }

        $template = $content->getContentConfig()->hasViewTemplate()
            ? $content->getContentConfig()->getViewTemplate()
            : self::TEMPLATE_CONTENT_SCHEMA_TYPE;

        return $platform->formatResponse(
            $request,
            ["content" => $content->toArray()],
            $template
        );
    }

    private function handleContentViewIndex(ServerRequestInterface $request, PlatformInterface $platform, Content $content): ResponseInterface
    {
        $collection = Select::from($content->getSchemaIdentifier());
        $template = $content->getContentConfig()->hasIndexTemplate()
            ? $content->getContentConfig()->getIndexTemplate()
            : self::TEMPLATE_CONTENT_SCHEMA_TYPE_INDEX;

        return $platform->formatResponse(
            $request,
            ["collection" => $collection->fetchAllLazy()],
            $template
        );
    }

    private function handleContentEditView(
        ServerRequestInterface $request,
        PlatformInterface $platform,
        Content $content
    ): ResponseInterface {
        $form = ContentForm::create($content);
        $form->setData($content->toArray());

        // @todo form handling

        return $platform->formatResponse($request);
    }

    private function handleContentUpdate(
        ServerRequestInterface $request,
        PlatformInterface $platform,
        Content $content
    ): ResponseInterface {
        $form = ContentForm::create($content);
        $form->setData($content->toArray());

        // @todo handle form

        return $platform->formatResponse($request);
    }

    private function hydrateContent(ServerRequestInterface $request, Content $content): Content
    {
        $select = Select::from($content->getSchemaIdentifier());
        $params = $request->getQueryParams();
        foreach ($params as $key => $value) {
            if (! $content->hasProperty($key)) {
                continue;
            }

            if ($content->isRelationProperty($key)) {
                continue;
            }

            $select->where($key, $value);
        }
        
        return $select->fetch();
    }
}
