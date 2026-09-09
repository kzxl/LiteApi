<?php

declare(strict_types=1);

namespace LiteApi\Tests;

use PHPUnit\Framework\TestCase;
use LiteApi\Http\ApiResponse;
use Nyholm\Psr7\Response;

class ApiResponseTest extends TestCase
{
    public function testOkResponse(): void
    {
        $response = new Response();
        $res = ApiResponse::ok($response, ['id' => 1, 'name' => 'Phong'], ['version' => '1.0']);

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('application/json; charset=utf-8', $res->getHeaderLine('Content-Type'));

        $body = json_decode((string) $res->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertSame(1, $body['data']['id']);
        $this->assertSame('1.0', $body['meta']['version']);
    }

    public function testCreatedResponse(): void
    {
        $response = new Response();
        $res = ApiResponse::created($response, ['id' => 10], location: '/api/users/10');

        $this->assertSame(201, $res->getStatusCode());
        $this->assertSame('/api/users/10', $res->getHeaderLine('Location'));

        $body = json_decode((string) $res->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertSame(10, $body['data']['id']);
    }

    public function testNoContentResponse(): void
    {
        $response = new Response();
        $res = ApiResponse::noContent($response);

        $this->assertSame(204, $res->getStatusCode());
    }

    public function testErrorResponse(): void
    {
        $response = new Response();
        $res = ApiResponse::error(
            response: $response,
            message: 'Invalid payload provided',
            status: 422,
            errorCode: 'VALIDATION_FAILED',
            details: ['field' => 'email is required']
        );

        $this->assertSame(422, $res->getStatusCode());
        $body = json_decode((string) $res->getBody(), true);

        $this->assertFalse($body['success']);
        $this->assertSame(422, $body['error']['code']);
        $this->assertSame('Invalid payload provided', $body['error']['message']);
        $this->assertSame('VALIDATION_FAILED', $body['error']['type']);
        $this->assertSame('email is required', $body['error']['details']['field']);
    }

    public function testPaginatedResponse(): void
    {
        $response = new Response();
        $paginatorData = [
            'data' => [['id' => 1], ['id' => 2]],
            'meta' => [
                'total' => 10,
                'current_page' => 1,
                'per_page' => 2,
                'last_page' => 5,
                'has_more' => true,
            ],
        ];

        $res = ApiResponse::paginated($response, $paginatorData);
        $this->assertSame(200, $res->getStatusCode());

        $body = json_decode((string) $res->getBody(), true);
        $this->assertTrue($body['success']);
        $this->assertCount(2, $body['data']);
        $this->assertSame(10, $body['meta']['total']);
        $this->assertTrue($body['meta']['has_more']);
    }
}
