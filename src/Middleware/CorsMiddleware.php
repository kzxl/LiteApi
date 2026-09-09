<?php

declare(strict_types=1);

namespace LiteApi\Middleware;

use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

/**
 * High-performance PSR-15 CORS (Cross-Origin Resource Sharing) Middleware.
 * Automatically resolves preflight OPTIONS requests without invoking subsequent middleware or route handlers.
 */
class CorsMiddleware implements MiddlewareInterface
{
    /** @var string[] */
    private array $allowedOrigins;

    /** @var string[] */
    private array $allowedMethods;

    /** @var string[] */
    private array $allowedHeaders;

    private bool $allowCredentials;
    private int $maxAge;

    /**
     * @param string[]|string $origins Allowed origins (e.g. ['*'] or ['http://localhost:3000'])
     * @param string[] $methods Allowed HTTP methods
     * @param string[] $headers Allowed request headers
     * @param bool $allowCredentials
     * @param int $maxAge Preflight cache duration in seconds
     * @param ResponseFactoryInterface|null $responseFactory Required for short-circuiting OPTIONS preflight
     */
    public function __construct(
        array|string $origins = ['*'],
        array $methods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
        array $headers = ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Origin'],
        bool $allowCredentials = false,
        int $maxAge = 86400,
        private readonly ?ResponseFactoryInterface $responseFactory = null,
    ) {
        $this->allowedOrigins = is_array($origins) ? $origins : [$origins];
        $this->allowedMethods = $methods;
        $this->allowedHeaders = $headers;
        $this->allowCredentials = $allowCredentials;
        $this->maxAge = $maxAge;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $origin = $request->getHeaderLine('Origin');

        // Check if preflight OPTIONS request
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            $response = $this->responseFactory
                ? $this->responseFactory->createResponse(204)
                : $handler->handle($request)->withStatus(204);

            return $this->applyCorsHeaders($response, $origin);
        }

        // Standard request -> execute downstream handler then apply CORS headers
        $response = $handler->handle($request);
        return $this->applyCorsHeaders($response, $origin);
    }

    private function applyCorsHeaders(ResponseInterface $response, string $origin): ResponseInterface
    {
        $allowedOrigin = $this->resolveAllowedOrigin($origin);

        if ($allowedOrigin !== null) {
            $response = $response->withHeader('Access-Control-Allow-Origin', $allowedOrigin);
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Max-Age', (string) $this->maxAge);

        if ($this->allowCredentials) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    private function resolveAllowedOrigin(string $origin): ?string
    {
        if (in_array('*', $this->allowedOrigins, true)) {
            return '*';
        }

        if ($origin !== '' && in_array($origin, $this->allowedOrigins, true)) {
            return $origin;
        }

        return $this->allowedOrigins[0] ?? null;
    }
}
