<?php

declare(strict_types=1);

namespace LiteApi\Tests;

use PHPUnit\Framework\TestCase;
use LiteApi\Middleware\JsonErrorHandler;
use Nyholm\Psr7\{Response, ServerRequest};
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpNotFoundException;

class JsonErrorHandlerTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    public function testHandlesHttpNotFoundException(): void
    {
        $handler = new JsonErrorHandler($this->factory);
        $request = new ServerRequest('GET', '/not-found');

        $middlewareHandler = new class($request) implements RequestHandlerInterface {
            public function __construct(private ServerRequestInterface $req) {}
            public function handle(ServerRequestInterface $request): ResponseInterface {
                throw new HttpNotFoundException($this->req);
            }
        };

        $response = $handler->process($request, $middlewareHandler);

        $this->assertSame(404, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);

        $this->assertFalse($body['success']);
        $this->assertSame(404, $body['error']['code']);
        $this->assertSame('NOT_FOUND', $body['error']['type']);
    }

    public function testHandlesInternalServerErrorMaskingInProduction(): void
    {
        $handler = new JsonErrorHandler($this->factory, displayErrorDetails: false);
        $request = new ServerRequest('GET', '/crash');

        $middlewareHandler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface {
                throw new \RuntimeException('Database credentials leaked in error message');
            }
        };

        $response = $handler->process($request, $middlewareHandler);

        $this->assertSame(500, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);

        $this->assertFalse($body['success']);
        $this->assertSame('An internal server error occurred.', $body['error']['message']);
        $this->assertArrayNotHasKey('details', $body['error']);
    }

    public function testRevealsDetailsInDebugMode(): void
    {
        $handler = new JsonErrorHandler($this->factory, displayErrorDetails: true);
        $request = new ServerRequest('GET', '/debug');

        $middlewareHandler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface {
                throw new \RuntimeException('Detailed debug information');
            }
        };

        $response = $handler->process($request, $middlewareHandler);

        $this->assertSame(500, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);

        $this->assertSame('Detailed debug information', $body['error']['message']);
        $this->assertArrayHasKey('details', $body['error']);
        $this->assertArrayHasKey('trace', $body['error']['details']);
    }
}
