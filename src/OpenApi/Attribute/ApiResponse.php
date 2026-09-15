<?php

declare(strict_types=1);

namespace LiteApi\OpenApi\Attribute;

use Attribute;

/**
 * Documents an HTTP response definition for an endpoint.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class ApiResponse
{
    /**
     * @param int $status HTTP status code (e.g. 200, 201, 400, 404, 500)
     * @param string $description Response description
     * @param string|array<string, mixed>|null $schema Class-string DTO or explicit JSON schema array
     * @param string $contentType MIME type (default 'application/json')
     */
    public function __construct(
        public readonly int $status = 200,
        public readonly string $description = 'Successful response',
        public readonly string|array|null $schema = null,
        public readonly string $contentType = 'application/json',
    ) {}
}
