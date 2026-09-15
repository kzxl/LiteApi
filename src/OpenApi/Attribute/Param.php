<?php

declare(strict_types=1);

namespace LiteApi\OpenApi\Attribute;

use Attribute;

/**
 * Documents a path or query parameter for an endpoint.
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Param
{
    /**
     * @param string $name Parameter name
     * @param string $in Parameter location: 'path', 'query', 'header', 'cookie'
     * @param string $description Explanation of parameter
     * @param bool $required Whether parameter is required (always true for 'path')
     * @param string $type Primitive type: 'string', 'integer', 'number', 'boolean', 'array'
     * @param mixed $default Default value if omitted
     * @param mixed $example Example value
     */
    public function __construct(
        public readonly string $name,
        public readonly string $in = 'query',
        public readonly string $description = '',
        public readonly bool $required = false,
        public readonly string $type = 'string',
        public readonly mixed $default = null,
        public readonly mixed $example = null,
    ) {}
}
