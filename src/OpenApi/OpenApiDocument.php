<?php

declare(strict_types=1);

namespace LiteApi\OpenApi;

use LiteApi\OpenApi\Attribute\ApiResponse;
use LiteApi\OpenApi\Attribute\Param;
use LiteApi\OpenApi\Attribute\RequestBody;
use LiteApi\OpenApi\Attribute\RouteDoc;
use LiteApi\OpenApi\Attribute\Security;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use ReflectionMethod;

/**
 * Sovereign OpenAPI 3.0.3 specification builder, controller scanner, and Swagger UI renderer.
 * Zero external dependencies.
 */
class OpenApiDocument
{
    private string $title;
    private string $version;
    private string $description;
    private array $servers = [];
    private array $paths = [];
    private array $schemas = [];
    private array $securitySchemes = [];

    public function __construct(
        string $title = 'LitePlatform API',
        string $version = '1.0.0',
        string $description = 'High-performance sovereign REST API'
    ) {
        $this->title = $title;
        $this->version = $version;
        $this->description = $description;

        // Default Bearer JWT authentication scheme
        $this->addBearerAuth();
    }

    public function addServer(string $url, string $description = ''): self
    {
        $this->servers[] = [
            'url'         => $url,
            'description' => $description,
        ];
        return $this;
    }

    public function addSecurityScheme(string $name, array $scheme): self
    {
        $this->securitySchemes[$name] = $scheme;
        return $this;
    }

    public function addBearerAuth(string $name = 'bearerAuth', string $description = 'JWT Authorization header'): self
    {
        $this->securitySchemes[$name] = [
            'type'         => 'http',
            'scheme'       => 'bearer',
            'bearerFormat' => 'JWT',
            'description'  => $description,
        ];
        return $this;
    }

    public function addSchema(string $name, array $schema): self
    {
        $this->schemas[$name] = $schema;
        return $this;
    }

    public function addRoute(string $method, string $path, array $operation): self
    {
        $method = strtolower($method);
        if (!isset($this->paths[$path])) {
            $this->paths[$path] = [];
        }
        $this->paths[$path][$method] = $operation;
        return $this;
    }

    /**
     * Scan a controller class for OpenAPI attributes and auto-register endpoints.
     *
     * @param class-string|object $controller
     */
    public function scanController(string|object $controller): self
    {
        $ref = new ReflectionClass($controller);

        // Class-level security if defined
        $classSecurity = [];
        foreach ($ref->getAttributes(Security::class) as $attr) {
            /** @var Security $sec */
            $sec = $attr->newInstance();
            $classSecurity[] = [$sec->name => $sec->scopes];
        }

        foreach ($ref->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $routeDocs = $method->getAttributes(RouteDoc::class);
            if (empty($routeDocs)) {
                continue;
            }

            /** @var RouteDoc $doc */
            $doc = $routeDocs[0]->newInstance();
            $httpMethod = strtolower($doc->method ?? $this->inferHttpMethod($method->getName()));
            $path = $doc->path ?? '/' . strtolower($method->getName());

            $operation = [
                'summary'     => $doc->summary,
                'description' => $doc->description,
                'tags'        => $doc->tags,
                'responses'   => [],
            ];

            if ($doc->operationId) {
                $operation['operationId'] = $doc->operationId;
            }

            if ($doc->deprecated) {
                $operation['deprecated'] = true;
            }

            // 1. Process Parameters (path, query, header)
            $params = [];
            foreach ($method->getAttributes(Param::class) as $paramAttr) {
                /** @var Param $p */
                $p = $paramAttr->newInstance();
                $paramDef = [
                    'name'        => $p->name,
                    'in'          => $p->in,
                    'description' => $p->description,
                    'required'    => $p->in === 'path' ? true : $p->required,
                    'schema'      => ['type' => $p->type],
                ];
                if ($p->default !== null) {
                    $paramDef['schema']['default'] = $p->default;
                }
                if ($p->example !== null) {
                    $paramDef['example'] = $p->example;
                }
                $params[] = $paramDef;
            }
            if (!empty($params)) {
                $operation['parameters'] = $params;
            }

            // 2. Process Request Body
            $requestBodies = $method->getAttributes(RequestBody::class);
            if (!empty($requestBodies)) {
                /** @var RequestBody $rb */
                $rb = $requestBodies[0]->newInstance();
                $schemaDef = $this->resolveSchemaReference($rb->schema);

                $operation['requestBody'] = [
                    'description' => $rb->description,
                    'required'    => $rb->required,
                    'content'     => [
                        $rb->contentType => [
                            'schema' => $schemaDef,
                        ],
                    ],
                ];
            }

            // 3. Process Responses
            $apiResponses = $method->getAttributes(ApiResponse::class);
            if (!empty($apiResponses)) {
                foreach ($apiResponses as $respAttr) {
                    /** @var ApiResponse $resp */
                    $resp = $respAttr->newInstance();
                    $statusCode = (string)$resp->status;

                    $respDef = [
                        'description' => $resp->description,
                    ];

                    if ($resp->schema !== null) {
                        $schemaDef = $this->resolveSchemaReference($resp->schema);
                        $respDef['content'] = [
                            $resp->contentType => [
                                'schema' => $schemaDef,
                            ],
                        ];
                    }

                    $operation['responses'][$statusCode] = $respDef;
                }
            } else {
                $operation['responses']['200'] = ['description' => 'Successful operation'];
            }

            // 4. Process Security
            $methodSecurity = [];
            foreach ($method->getAttributes(Security::class) as $secAttr) {
                /** @var Security $sec */
                $sec = $secAttr->newInstance();
                $methodSecurity[] = [$sec->name => $sec->scopes];
            }

            if (!empty($methodSecurity)) {
                $operation['security'] = $methodSecurity;
            } elseif (!empty($classSecurity)) {
                $operation['security'] = $classSecurity;
            }

            $this->addRoute($httpMethod, $path, $operation);
        }

        return $this;
    }

    /**
     * Scan multiple controller classes.
     *
     * @param array<class-string|object> $controllers
     */
    public function scanControllers(array $controllers): self
    {
        foreach ($controllers as $ctrl) {
            $this->scanController($ctrl);
        }
        return $this;
    }

    /**
     * Convert specification to complete OpenAPI 3.0.3 array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $spec = [
            'openapi' => '3.0.3',
            'info'    => [
                'title'       => $this->title,
                'version'     => $this->version,
                'description' => $this->description,
            ],
            'paths'   => $this->paths,
        ];

        if (!empty($this->servers)) {
            $spec['servers'] = $this->servers;
        }

        $components = [];
        if (!empty($this->schemas)) {
            $components['schemas'] = $this->schemas;
        }
        if (!empty($this->securitySchemes)) {
            $components['securitySchemes'] = $this->securitySchemes;
        }

        if (!empty($components)) {
            $spec['components'] = $components;
        }

        return $spec;
    }

    public function toJson(int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE): string
    {
        return (string)json_encode($this->toArray(), $flags);
    }

    public function toJsonResponse(ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write($this->toJson());
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Render self-contained, high-performance Swagger UI HTML page via official CDN.
     */
    public function renderSwaggerUi(string $specUrl = '/openapi.json', string $title = 'API Documentation'): string
    {
        $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $escapedUrl = htmlspecialchars($specUrl, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$escapedTitle}</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css" />
    <style>
        html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }
        *, *:before, *:after { box-sizing: inherit; }
        body { margin: 0; background: #fafafa; }
        .topbar { display: none; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script>
    window.onload = function() {
        SwaggerUIBundle({
            url: "{$escapedUrl}",
            dom_id: '#swagger-ui',
            deepLinking: true,
            presets: [SwaggerUIBundle.presets.apis],
            layout: "BaseLayout"
        });
    };
    </script>
</body>
</html>
HTML;
    }

    public function toSwaggerUiResponse(
        ResponseInterface $response,
        string $specUrl = '/openapi.json',
        string $title = 'API Documentation'
    ): ResponseInterface {
        $html = $this->renderSwaggerUi($specUrl, $title);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Render Redoc HTML documentation viewer via official CDN.
     */
    public function renderRedoc(string $specUrl = '/openapi.json', string $title = 'API Documentation'): string
    {
        $escapedTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $escapedUrl = htmlspecialchars($specUrl, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>{$escapedTitle}</title>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css?family=Montserrat:300,400,700|Roboto:300,400,700" rel="stylesheet">
    <style>body { margin: 0; padding: 0; }</style>
</head>
<body>
    <redoc spec-url="{$escapedUrl}"></redoc>
    <script src="https://cdn.redoc.ly/redoc/latest/bundles/redoc.standalone.js"></script>
</body>
</html>
HTML;
    }

    public function toRedocResponse(
        ResponseInterface $response,
        string $specUrl = '/openapi.json',
        string $title = 'API Documentation'
    ): ResponseInterface {
        $html = $this->renderRedoc($specUrl, $title);
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    private function resolveSchemaReference(string|array|null $schema): array
    {
        if ($schema === null) {
            return ['type' => 'object'];
        }

        if (is_array($schema)) {
            return $schema;
        }

        if (class_exists($schema)) {
            $shortName = SchemaExtractor::getShortName($schema);
            if (!isset($this->schemas[$shortName])) {
                $this->schemas[$shortName] = SchemaExtractor::extract($schema);
            }
            return ['$ref' => '#/components/schemas/' . $shortName];
        }

        return ['type' => 'string'];
    }

    private function inferHttpMethod(string $actionName): string
    {
        $lower = strtolower($actionName);
        if (str_starts_with($lower, 'get') || str_starts_with($lower, 'index') || str_starts_with($lower, 'show') || str_starts_with($lower, 'list')) {
            return 'get';
        }
        if (str_starts_with($lower, 'post') || str_starts_with($lower, 'create') || str_starts_with($lower, 'store')) {
            return 'post';
        }
        if (str_starts_with($lower, 'put') || str_starts_with($lower, 'update')) {
            return 'put';
        }
        if (str_starts_with($lower, 'delete') || str_starts_with($lower, 'destroy') || str_starts_with($lower, 'remove')) {
            return 'delete';
        }
        if (str_starts_with($lower, 'patch')) {
            return 'patch';
        }
        return 'get';
    }
}
