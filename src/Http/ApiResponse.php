<?php

declare(strict_types=1);

namespace LiteApi\Http;

use Psr\Http\Message\ResponseInterface;

/**
 * Standardized JSON API Response Builder for PSR-7 / Slim 4.
 */
class ApiResponse
{
    /**
     * Return a 200 OK standard JSON response.
     *
     * @param ResponseInterface $response
     * @param mixed $data
     * @param array<string, mixed> $meta
     * @param int $status
     * @return ResponseInterface
     */
    public static function ok(
        ResponseInterface $response,
        mixed $data = null,
        array $meta = [],
        int $status = 200,
    ): ResponseInterface {
        $payload = ['success' => true];
        if ($data !== null) {
            $payload['data'] = $data;
        }
        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        return self::json($response, $payload, $status);
    }

    /**
     * Return a 201 Created standard JSON response.
     */
    public static function created(
        ResponseInterface $response,
        mixed $data = null,
        ?string $location = null,
    ): ResponseInterface {
        $res = self::ok($response, $data, status: 201);
        if ($location !== null) {
            $res = $res->withHeader('Location', $location);
        }
        return $res;
    }

    /**
     * Return a 204 No Content response.
     */
    public static function noContent(ResponseInterface $response): ResponseInterface
    {
        return $response->withStatus(204);
    }

    /**
     * Return a standardized paginated response (compatible with LiteORM Paginator or standard arrays).
     */
    public static function paginated(
        ResponseInterface $response,
        mixed $paginator,
        int $status = 200,
    ): ResponseInterface {
        if (is_object($paginator) && method_exists($paginator, 'toArray')) {
            $array = $paginator->toArray();
            return self::json($response, [
                'success' => true,
                'data' => $array['data'] ?? [],
                'meta' => $array['meta'] ?? [],
            ], $status);
        }

        if (is_array($paginator)) {
            return self::json($response, [
                'success' => true,
                'data' => $paginator['data'] ?? $paginator['items'] ?? $paginator,
                'meta' => $paginator['meta'] ?? [],
            ], $status);
        }

        return self::ok($response, $paginator, status: $status);
    }

    /**
     * Return a standardized error JSON response.
     *
     * @param ResponseInterface $response
     * @param string $message
     * @param int $status
     * @param string|null $errorCode Machine-readable error code (e.g. 'VALIDATION_FAILED')
     * @param mixed $details
     * @return ResponseInterface
     */
    public static function error(
        ResponseInterface $response,
        string $message,
        int $status = 400,
        ?string $errorCode = null,
        mixed $details = null,
    ): ResponseInterface {
        $error = [
            'code' => $status,
            'message' => $message,
        ];

        if ($errorCode !== null) {
            $error['type'] = $errorCode;
        }

        if ($details !== null) {
            $error['details'] = $details;
        }

        return self::json($response, [
            'success' => false,
            'error' => $error,
        ], $status);
    }

    /**
     * Helper to encode payload and write to PSR-7 stream.
     */
    public static function json(ResponseInterface $response, mixed $payload, int $status = 200): ResponseInterface
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $json = '{"success":false,"error":{"code":500,"message":"JSON encoding error"}}';
            $status = 500;
        }

        $body = $response->getBody();
        $body->rewind();
        $body->write($json);

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
