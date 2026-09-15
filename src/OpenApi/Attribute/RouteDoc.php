<?php

declare(strict_types=1);

namespace LiteApi\OpenApi\Attribute;

use Attribute;

/**
 * Documents an API endpoint route for OpenAPI 3.0 specification.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class RouteDoc
{
    /**
     * @param string $summary Brief summary of what the endpoint does
     * @param string $description Verbose explanation of endpoint behavior
     * @param array<string> $tags Grouping tags (e.g. ['Users', 'Auth'])
     * @param string|null $operationId Unique identifier for the operation
     * @param string|null $method HTTP method (GET, POST, PUT, DELETE, PATCH). If null, inferred from router or name.
     * @param string|null $path Route path (e.g. '/api/users/{id}').
     * @param bool $deprecated Mark endpoint as deprecated
     */
    public function __construct(
        public readonly string $summary = '',
        public readonly string $description = '',
        public readonly array $tags = [],
        public readonly ?string $operationId = null,
        public readonly ?string $method = null,
        public readonly ?string $path = null,
        public readonly bool $deprecated = false,
    ) {}
}
