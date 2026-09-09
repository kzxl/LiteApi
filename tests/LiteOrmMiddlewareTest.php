<?php

declare(strict_types=1);

namespace LiteApi\Tests;

use PHPUnit\Framework\TestCase;
use LiteApi\Middleware\LiteOrmMiddleware;
use LiteORM\EntityManager;
use LiteORM\Attribute\{Entity, Table, Column, Id, AutoIncrement};
use Nyholm\Psr7\{Response, ServerRequest};
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;

#[Entity]
#[Table('test_api_users')]
class ApiTestUser
{
    #[Id, AutoIncrement]
    public int $id;

    #[Column]
    public string $name;

    #[Column(unique: true)]
    public string $email;
}

class LiteOrmMiddlewareTest extends TestCase
{
    private EntityManager $em;

    protected function setUp(): void
    {
        $this->em = new EntityManager('sqlite::memory:');
        $this->em->createTable(ApiTestUser::class);
    }

    public function testInjectsEntityManagerAttribute(): void
    {
        $middleware = new LiteOrmMiddleware($this->em);
        $request = new ServerRequest('GET', '/api/users');

        $injectedEm = null;
        $handler = new class(function ($em) use (&$injectedEm) { $injectedEm = $em; }) implements RequestHandlerInterface {
            public function __construct(private $callback) {}
            public function handle(ServerRequestInterface $request): ResponseInterface {
                ($this->callback)($request->getAttribute('em'));
                return (new Response())->withStatus(200);
            }
        };

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($this->em, $injectedEm);
    }

    public function testAutoFlushOnSuccess(): void
    {
        $middleware = new LiteOrmMiddleware($this->em, autoFlush: true);
        $request = new ServerRequest('POST', '/api/users');

        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface {
                /** @var EntityManager $em */
                $em = $request->getAttribute('em');
                $user = new ApiTestUser();
                $user->name = 'AutoFlush User';
                $user->email = 'autoflush@example.com';
                $em->persist($user);

                return (new Response())->withStatus(201);
            }
        };

        $response = $middleware->process($request, $handler);
        $this->assertSame(201, $response->getStatusCode());

        // Verify entity was automatically persisted into SQLite database
        $found = $this->em->query(ApiTestUser::class)->where('email', 'autoflush@example.com')->first();
        $this->assertNotNull($found);
        $this->assertSame('AutoFlush User', $found->name);
    }
}
