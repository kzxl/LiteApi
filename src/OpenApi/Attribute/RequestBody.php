<?php

declare(strict_types=1);

namespace LiteApi\OpenApi\Attribute;

use Attribute;

/**
 * Documents the request body payload for POST/PUT/PATCH endpoints.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class RequestBody
{
    /**
     * @param string|array<string, mixed>|null $schema Class-string DTO or explicit JSON schema array
     * @param string $description Explanation of request body
     * @param bool $required Whether request body is mandatory
     * @param string $contentType MIME type (default 'application/json')
     */
    public function __construct(
        public readonly string|array|null $schema = null,
        public readonly string $description = '',
        public readonly bool $required = true,
        public readonly string $contentType = 'application/json',
    ) {}
}
