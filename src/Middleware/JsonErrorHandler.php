<?php

declare(strict_types=1);

namespace LiteApi\Middleware;

use LiteApi\Http\ApiResponse;
use Psr\Http\Message\{ResponseFactoryInterface, ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;
use Slim\Interfaces\ErrorHandlerInterface;
use Throwable;

/**
 * Enterprise JSON Error Handler & PSR-15 Middleware for Slim 4 and microservices.
 * Prevents HTML error leakage, provides consistent API error envelopes, and masks internal errors in production.
 */
class JsonErrorHandler implements MiddlewareInterface, ErrorHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly bool $displayErrorDetails = false,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    /**
     * PSR-15 Middleware implementation: catches any uncaught downstream exception.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $e) {
            return $this->handleException($request, $e);
        }
    }

    /**
     * Slim 4 ErrorHandlerInterface implementation.
     */
    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails,
    ): ResponseInterface {
        if ($logErrors && $this->logger) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
        }

        return $this->handleException($request, $exception, $displayErrorDetails);
    }

    public function handleException(
        ServerRequestInterface $request,
        Throwable $e,
        ?bool $showDetails = null,
    ): ResponseInterface {
        $showDetails ??= $this->displayErrorDetails;

        $status = 500;
        $type = 'INTERNAL_SERVER_ERROR';
        $message = 'An internal server error occurred.';

        if ($e instanceof HttpException) {
            $status = $e->getCode();
            $message = $e->getMessage();
            $type = match ($status) {
                400 => 'BAD_REQUEST',
                401 => 'UNAUTHORIZED',
                403 => 'FORBIDDEN',
                404 => 'NOT_FOUND',
                405 => 'METHOD_NOT_ALLOWED',
                409 => 'CONFLICT',
                422 => 'UNPROCESSABLE_ENTITY',
                default => 'HTTP_ERROR',
            };
        } elseif ($e instanceof \InvalidArgumentException) {
            $status = 400;
            $type = 'INVALID_ARGUMENT';
            $message = $e->getMessage();
        } elseif ($showDetails) {
            $message = $e->getMessage();
            $type = get_class($e);
        }

        $details = null;
        if ($showDetails) {
            $details = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString()),
            ];
        }

        $response = $this->responseFactory->createResponse($status);
        return ApiResponse::error(
            response: $response,
            message: $message,
            status: $status,
            errorCode: $type,
            details: $details,
        );
    }
}
