<?php

declare(strict_types=1);

namespace LiteApi\Middleware;

use LiteApi\Http\ApiResponse;
use LiteApi\Security\Jwt;
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

/**
 * PSR-15 Middleware for Bearer JWT Authentication.
 * Validates token, enforces expiration, injects user claims into request attributes, and protects private routes.
 */
class JwtAuthMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    private array $publicRoutes;

    /**
     * @param string $secret HMAC-SHA256 secret key
     * @param ResponseFactoryInterface $responseFactory
     * @param string[] $publicRoutes Route path prefixes that bypass authentication (e.g. ['/api/auth', '/health'])
     * @param string $attributeName Request attribute name to store decoded user claims (default: 'user')
     */
    public function __construct(
        private readonly string $secret,
        private readonly ResponseFactoryInterface $responseFactory,
        array $publicRoutes = [],
        private readonly string $attributeName = 'user',
    ) {
        $this->publicRoutes = $publicRoutes;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        // 1. Check if current route is whitelisted
        foreach ($this->publicRoutes as $publicPrefix) {
            if (str_starts_with($path, $publicPrefix)) {
                return $handler->handle($request);
            }
        }

        // 2. Extract Authorization Header
        $authHeader = $request->getHeaderLine('Authorization');
        if (!preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            $response = $this->responseFactory->createResponse(401);
            return ApiResponse::error(
                response: $response,
                message: 'Missing or malformed Bearer authorization token',
                status: 401,
                errorCode: 'UNAUTHORIZED'
            );
        }

        $token = $matches[1];

        // 3. Verify & Decode Token
        try {
            $payload = Jwt::decode($token, $this->secret);
        } catch (\Throwable $e) {
            $response = $this->responseFactory->createResponse(401);
            return ApiResponse::error(
                response: $response,
                message: 'Unauthorized: ' . $e->getMessage(),
                status: 401,
                errorCode: 'INVALID_TOKEN'
            );
        }

        // 4. Inject payload into request and proceed
        $requestWithUser = $request
            ->withAttribute($this->attributeName, $payload)
            ->withAttribute('token', $token);

        return $handler->handle($requestWithUser);
    }
}
