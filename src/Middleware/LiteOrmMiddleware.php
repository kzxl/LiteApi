<?php

declare(strict_types=1);

namespace LiteApi\Middleware;

use LiteORM\EntityManager;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

/**
 * PSR-15 Middleware bridge for LiteORM.
 * Manages per-request EntityManager lifecycle, auto-flushes mutations on success,
 * and clears identity map snapshots on completion to prevent memory leaks in persistent workers (FrankenPHP/RoadRunner).
 */
class LiteOrmMiddleware implements MiddlewareInterface
{
    /**
     * @param EntityManager $em
     * @param bool $autoFlush If true, automatically calls $em->flush() on successful response (status < 400)
     * @param string $attributeName Request attribute name (default: 'em')
     */
    public function __construct(
        private readonly EntityManager $em,
        private readonly bool $autoFlush = false,
        private readonly string $attributeName = 'em',
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Inject EntityManager into request attribute
        $requestWithEm = $request->withAttribute($this->attributeName, $this->em);

        try {
            $response = $handler->handle($requestWithEm);

            // Auto-flush pending mutations if response is successful
            if ($this->autoFlush && $response->getStatusCode() < 400) {
                $this->em->flush();
            }

            return $response;
        } finally {
            // Crucial for FrankenPHP / RoadRunner: reset identity map and snapshots to prevent memory leak
            $this->em->clear();
        }
    }
}
