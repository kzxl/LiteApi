<?php

declare(strict_types=1);

namespace LiteApi\Tests;

use PHPUnit\Framework\TestCase;
use LiteApi\Middleware\JwtAuthMiddleware;
use LiteApi\Security\Jwt;
use Nyholm\Psr7\{Response, ServerRequest};
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;

class JwtAuthMiddlewareTest extends TestCase
{
    private const SECRET = 'auth-secret-key-xyz-987';
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    public function testPublicRouteBypassesAuth(): void
    {
        $middleware = new JwtAuthMiddleware(
            secret: self::SECRET,
            responseFactory: $this->factory,
            publicRoutes: ['/api/public', '/health']
        );

        $request = new ServerRequest('GET', '/api/public/status');

        $handler = new class implements RequestHandlerInterface {
            public bool $called = false;
            public function handle(ServerRequestInterface $request): ResponseInterface {
                $this->called = true;
                return (new Response())->withStatus(200);
            }
        };

        $response = $middleware->process($request, $handler);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handler->called);
    }

    public function testMissingTokenReturnsUnauthorized(): void
    {
        $middleware = new JwtAuthMiddleware(
            secret: self::SECRET,
            responseFactory: $this->factory
        );

        $request = new ServerRequest('GET', '/api/private/profile');

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface {
                return (new Response())->withStatus(200);
            }
        };

        $response = $middleware->process($request, $handler);
        $this->assertSame(401, $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('UNAUTHORIZED', $body['error']['type']);
    }

    public function testValidTokenInjectsUserAttribute(): void
    {
        $middleware = new JwtAuthMiddleware(
            secret: self::SECRET,
            responseFactory: $this->factory
        );

        $token = Jwt::sign(['id' => 123, 'email' => 'user@test.com'], self::SECRET);

        $request = (new ServerRequest('GET', '/api/private/profile'))
            ->withHeader('Authorization', "Bearer {$token}");

        $injectedUser = null;
        $handler = new class(function ($u) use (&$injectedUser) { $injectedUser = $u; }) implements RequestHandlerInterface {
            public function __construct(private $callback) {}
            public function handle(ServerRequestInterface $request): ResponseInterface {
                ($this->callback)($request->getAttribute('user'));
                return (new Response())->withStatus(200);
            }
        };

        $response = $middleware->process($request, $handler);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($injectedUser);
        $this->assertSame(123, $injectedUser['id']);
        $this->assertSame('user@test.com', $injectedUser['email']);
    }
}
