<?php

declare(strict_types=1);

namespace LiteApi\Security;

/**
 * Pure PHP 8.2 zero-dependency JSON Web Token (JWT) engine.
 * Supports HS256 (HMAC-SHA256) signature verification with timing-attack mitigation.
 */
class Jwt
{
    /**
     * Sign a payload array into a JWT string.
     *
     * @param array<string, mixed> $payload
     * @param string $secret
     * @param int $ttl Lifetime in seconds (default: 3600 = 1 hour)
     * @param array<string, mixed> $extraHeaders
     * @return string
     */
    public static function sign(
        array $payload,
        string $secret,
        int $ttl = 3600,
        array $extraHeaders = [],
    ): string {
        $now = time();
        $payload['iat'] ??= $now;
        if (!isset($payload['exp']) && $ttl !== 0) {
            $payload['exp'] = $now + $ttl;
        }

        $header = array_merge([
            'typ' => 'JWT',
            'alg' => 'HS256',
        ], $extraHeaders);

        $headerB64 = self::base64UrlEncode((string) json_encode($header));
        $payloadB64 = self::base64UrlEncode((string) json_encode($payload));

        $signature = hash_hmac('sha256', "{$headerB64}.{$payloadB64}", $secret, true);
        $signatureB64 = self::base64UrlEncode($signature);

        return "{$headerB64}.{$payloadB64}.{$signatureB64}";
    }

    /**
     * Verify and decode a JWT string.
     *
     * @throws \InvalidArgumentException When token is malformed or invalid
     * @throws \RuntimeException When token has expired or signature mismatch
     * @return array<string, mixed> The decoded payload
     */
    public static function decode(string $token, string $secret): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Malformed JWT token: must contain 3 parts');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        // Verify header algorithm to prevent algorithm confusion attacks (e.g. 'none' or asymmetric mismatch)
        $headerJson = self::base64UrlDecode($headerB64);
        $header = json_decode($headerJson, true);
        if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256') {
            throw new \InvalidArgumentException('Unsupported or invalid JWT algorithm');
        }

        // Verify signature
        $expectedSignature = hash_hmac('sha256', "{$headerB64}.{$payloadB64}", $secret, true);
        $givenSignature = self::base64UrlDecode($signatureB64);

        if (!hash_equals($expectedSignature, $givenSignature)) {
            throw new \RuntimeException('Invalid JWT signature');
        }

        $payloadJson = self::base64UrlDecode($payloadB64);
        $payload = json_decode($payloadJson, true);

        if (!is_array($payload)) {
            throw new \InvalidArgumentException('Invalid JWT payload encoding');
        }

        $now = time();

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < $now) {
            throw new \RuntimeException('JWT token has expired');
        }

        // Check not-before
        if (isset($payload['nbf']) && $payload['nbf'] > $now) {
            throw new \RuntimeException('JWT token is not yet valid');
        }

        return $payload;
    }

    /**
     * Fast boolean check whether a token is valid and active.
     */
    public static function verify(string $token, string $secret): bool
    {
        try {
            self::decode($token, $secret);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Decode payload without verifying signature (for debugging or inspection).
     * @return array<string, mixed>|null
     */
    public static function unsafeDecode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        $json = self::base64UrlDecode($parts[1]);
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padLen = 4 - $remainder;
            $data .= str_repeat('=', $padLen);
        }
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
