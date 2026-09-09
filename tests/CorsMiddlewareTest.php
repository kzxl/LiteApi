<?php

declare(strict_types=1);

namespace LiteApi\Tests;

use PHPUnit\Framework\TestCase;
use LiteApi\Middleware\CorsMiddleware;
use Nyholm\Psr7\{Response, ServerRequest};
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;

class CorsMiddlewareTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    public function testPreflightOptionsRequestShortCircuits(): void
    {
        $cors = new CorsMiddleware(
            origins: ['http://localhost:3000'],
            responseFactory: $this->factory
        );

        $request = (new ServerRequest('OPTIONS', '/api/users'))
            ->withHeader('Origin', 'http://localhost:3000');

        $handler = new class implements RequestHandlerInterface {
            public bool $called = false;
            public function handle(ServerRequestInterface $request): ResponseInterface {
                $this->called = true;
                return (new Response())->withStatus(200);
            }
        };

        $response = $cors->process($request, $handler);

        // Preflight should return 204 without calling downstream handler
        $this->assertSame(204, $response->getStatusCode());
        $this->assertFalse($handler->called);
        $this->assertSame('http://localhost:3000', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertStringContainsString('GET', $response->getHeaderLine('Access-Control-Allow-Methods'));
    }

    public function testStandardGetRequestPassesThroughAndAddsCorsHeaders(): void
    {
        $cors = new CorsMiddleware(
            origins: ['*'],
            allowCredentials: true
        );

        $request = (new ServerRequest('GET', '/api/users'))
            ->withHeader('Origin', 'https://mycompany.com');

        $handler = new class implements RequestHandlerInterface {
            public bool $called = false;
            public function handle(ServerRequestInterface $request): ResponseInterface {
                $this->called = true;
                $res = new Response();
                $res->getBody()->write('hello world');
                return $res;
            }
        };

        $response = $cors->process($request, $handler);

        $this->assertTrue($handler->called);
        $this->assertSame('*', $response->getHeaderLine('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->getHeaderLine('Access-Control-Allow-Credentials'));
        $this->assertSame('hello world', (string) $response->getBody());
    }
}
