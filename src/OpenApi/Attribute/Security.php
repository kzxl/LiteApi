<?php

declare(strict_types=1);

namespace LiteApi\OpenApi\Attribute;

use Attribute;

/**
 * Documents a security requirement for an endpoint (e.g. JWT Bearer token).
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Security
{
    /**
     * @param string $name Security scheme name (default 'bearerAuth')
     * @param array<string> $scopes Required scopes if using OAuth2
     */
    public function __construct(
        public readonly string $name = 'bearerAuth',
        public readonly array $scopes = [],
    ) {}
}
