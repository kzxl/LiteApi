<?php

declare(strict_types=1);

namespace LiteApi\Tests\Unit;

use LiteApi\OpenApi\Attribute\ApiResponse;
use LiteApi\OpenApi\Attribute\Param;
use LiteApi\OpenApi\Attribute\RequestBody;
use LiteApi\OpenApi\Attribute\RouteDoc;
use LiteApi\OpenApi\Attribute\Security;
use LiteApi\OpenApi\OpenApiDocument;
use LiteApi\OpenApi\SchemaExtractor;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;

class SampleUserDTO
{
    public int $id;
    public string $name;
    public ?string $email = null;
    public bool $active = true;
}

class CreateUserDTO
{
    public string $username;
    public string $password;
    public float $initialBalance = 0.0;
}

class SampleUserController
{
    #[RouteDoc(summary: 'List users', tags: ['Users'], method: 'GET', path: '/api/users')]
    #[Param(name: 'page', in: 'query', description: 'Page number', type: 'integer', default: 1)]
    #[Param(name: 'limit', in: 'query', description: 'Page limit', type: 'integer', default: 20)]
    #[ApiResponse(status: 200, description: 'User list', schema: SampleUserDTO::class)]
    #[Security('bearerAuth')]
    public function list(): void {}

    #[RouteDoc(summary: 'Create user', tags: ['Users'], method: 'POST', path: '/api/users')]
    #[RequestBody(schema: CreateUserDTO::class, description: 'New user payload')]
    #[ApiResponse(status: 201, description: 'User created', schema: SampleUserDTO::class)]
    #[ApiResponse(status: 400, description: 'Validation error')]
    public function create(): void {}
}

class OpenApiTest extends TestCase
{
    public function testSchemaExtractor(): void
    {
        $schema = SchemaExtractor::extract(SampleUserDTO::class);

        $this->assertEquals('object', $schema['type']);
        $this->assertArrayHasKey('properties', $schema);
        $this->assertEquals('integer', $schema['properties']['id']['type']);
        $this->assertEquals('string', $schema['properties']['name']['type']);
        $this->assertEquals('string', $schema['properties']['email']['type']);
        $this->assertTrue($schema['properties']['email']['nullable']);
        $this->assertEquals('boolean', $schema['properties']['active']['type']);

        // 'id' and 'name' are required; 'email' and 'active' have default/null
        $this->assertEquals(['id', 'name'], $schema['required']);
    }

    public function testOpenApiDocumentControllerScan(): void
    {
        $doc = new OpenApiDocument('Test API', '2.0.0', 'Test Description');
        $doc->addServer('https://api.example.com', 'Production Server');
        $doc->scanController(SampleUserController::class);

        $spec = $doc->toArray();

        $this->assertEquals('3.0.3', $spec['openapi']);
        $this->assertEquals('Test API', $spec['info']['title']);
        $this->assertEquals('2.0.0', $spec['info']['version']);
        $this->assertCount(1, $spec['servers']);

        // Verify paths
        $this->assertArrayHasKey('/api/users', $spec['paths']);
        $this->assertArrayHasKey('get', $spec['paths']['/api/users']);
        $this->assertArrayHasKey('post', $spec['paths']['/api/users']);

        $getOp = $spec['paths']['/api/users']['get'];
        $this->assertEquals('List users', $getOp['summary']);
        $this->assertEquals(['Users'], $getOp['tags']);
        $this->assertCount(2, $getOp['parameters']);
        $this->assertEquals('page', $getOp['parameters'][0]['name']);
        $this->assertEquals('query', $getOp['parameters'][0]['in']);
        $this->assertEquals([['bearerAuth' => []]], $getOp['security']);

        // Verify Request Body and Components Schemas
        $postOp = $spec['paths']['/api/users']['post'];
        $this->assertArrayHasKey('requestBody', $postOp);
        $this->assertEquals('#/components/schemas/CreateUserDTO', $postOp['requestBody']['content']['application/json']['schema']['$ref']);

        $this->assertArrayHasKey('components', $spec);
        $this->assertArrayHasKey('schemas', $spec['components']);
        $this->assertArrayHasKey('CreateUserDTO', $spec['components']['schemas']);
        $this->assertArrayHasKey('SampleUserDTO', $spec['components']['schemas']);
    }

    public function testJsonAndSwaggerUiOutput(): void
    {
        $doc = new OpenApiDocument();
        $doc->scanController(SampleUserController::class);

        $json = $doc->toJson();
        $this->assertJson($json);
        $this->assertStringContainsString('/api/users', $json);

        $html = $doc->renderSwaggerUi('/api/spec.json', 'My Swagger UI');
        $this->assertStringContainsString('My Swagger UI', $html);
        $this->assertStringContainsString('/api/spec.json', $html);
        $this->assertStringContainsString('swagger-ui-bundle.js', $html);

        $redocHtml = $doc->renderRedoc('/api/spec.json', 'My Redoc');
        $this->assertStringContainsString('redoc.standalone.js', $redocHtml);
    }

    public function testPsr7Responses(): void
    {
        $doc = new OpenApiDocument();
        $doc->scanController(SampleUserController::class);

        $response = new Response();

        $jsonResp = $doc->toJsonResponse($response);
        $this->assertEquals('application/json; charset=utf-8', $jsonResp->getHeaderLine('Content-Type'));
        $this->assertJson((string)$jsonResp->getBody());

        $swaggerResp = $doc->toSwaggerUiResponse(new Response());
        $this->assertEquals('text/html; charset=utf-8', $swaggerResp->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('swagger-ui', (string)$swaggerResp->getBody());
    }
}
