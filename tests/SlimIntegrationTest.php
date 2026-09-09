<?php

declare(strict_types=1);

namespace LiteApi\Tests;

use PHPUnit\Framework\TestCase;
use LiteApi\Http\ApiResponse;
use LiteApi\Middleware\{CorsMiddleware, JsonErrorHandler, JwtAuthMiddleware};
use LiteApi\Security\Jwt;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Slim\Factory\AppFactory;

class SlimIntegrationTest extends TestCase
{
    private const JWT_SECRET = 'super-secret-production-key-999';

    public function testSlim4AppWithLiteApiSuite(): void
    {
        $factory = new Psr17Factory();
        AppFactory::setResponseFactory($factory);
        $app = AppFactory::create();

        // 1. Add CORS
        $app->add(new CorsMiddleware(
            origins: ['http://localhost:3000'],
            responseFactory: $factory
        ));

        // 2. Add JWT Auth protecting all routes except /api/public
        $app->add(new JwtAuthMiddleware(
            secret: self::JWT_SECRET,
            responseFactory: $factory,
            publicRoutes: ['/api/public']
        ));

        // 3. Define Routes
        $app->get('/api/public/health', function (ServerRequestInterface $req, ResponseInterface $res) {
            return ApiResponse::ok($res, ['status' => 'healthy'], ['uptime' => 12345]);
        });

        $app->get('/api/me', function (ServerRequestInterface $req, ResponseInterface $res) {
            $user = $req->getAttribute('user');
            return ApiResponse::ok($res, $user);
        });

        // Test 1: Public route access
        $req1 = (new ServerRequest('GET', '/api/public/health'))
            ->withHeader('Origin', 'http://localhost:3000');
        $res1 = $app->handle($req1);

        $this->assertSame(200, $res1->getStatusCode());
        $this->assertSame('http://localhost:3000', $res1->getHeaderLine('Access-Control-Allow-Origin'));
        $body1 = json_decode((string) $res1->getBody(), true);
        $this->assertTrue($body1['success']);
        $this->assertSame('healthy', $body1['data']['status']);

        // Test 2: Protected route without token -> 401
        $req2 = new ServerRequest('GET', '/api/me');
        $res2 = $app->handle($req2);
        $this->assertSame(401, $res2->getStatusCode());

        // Test 3: Protected route with valid token -> 200 with user data
        $token = Jwt::sign(['id' => 99, 'name' => 'Phong Vo'], self::JWT_SECRET);
        $req3 = (new ServerRequest('GET', '/api/me'))
            ->withHeader('Authorization', "Bearer {$token}");
        $res3 = $app->handle($req3);

        $this->assertSame(200, $res3->getStatusCode());
        $body3 = json_decode((string) $res3->getBody(), true);
        $this->assertTrue($body3['success']);
        $this->assertSame(99, $body3['data']['id']);
        $this->assertSame('Phong Vo', $body3['data']['name']);
    }
}
