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
     * Sign a payload using RS256 (RSA with SHA-256) asymmetric key pair.
     *
     * @param array<string, mixed> $payload
     * @param \OpenSSLAsymmetricKey|string $privateKey PEM-encoded private key or key resource
     * @param int $ttl Lifetime in seconds (default: 3600 = 1 hour)
     * @param array<string, mixed> $extraHeaders
     * @param ?string $passphrase Optional passphrase if key is encrypted
     * @return string
     */
    public static function signRs256(
        array $payload,
        \OpenSSLAsymmetricKey|string $privateKey,
        int $ttl = 3600,
        array $extraHeaders = [],
        ?string $passphrase = null,
    ): string {
        $now = time();
        $payload['iat'] ??= $now;
        if (!isset($payload['exp']) && $ttl !== 0) {
            $payload['exp'] = $now + $ttl;
        }

        $header = array_merge([
            'typ' => 'JWT',
            'alg' => 'RS256',
        ], $extraHeaders);

        $headerB64 = self::base64UrlEncode((string) json_encode($header));
        $payloadB64 = self::base64UrlEncode((string) json_encode($payload));
        $dataToSign = "{$headerB64}.{$payloadB64}";

        $key = is_string($privateKey) && $passphrase !== null
            ? openssl_pkey_get_private($privateKey, $passphrase)
            : $privateKey;

        if ($key === false) {
            throw new \InvalidArgumentException('Invalid RSA private key or passphrase.');
        }

        $signature = '';
        $success = openssl_sign($dataToSign, $signature, $key, OPENSSL_ALGO_SHA256);
        if (!$success) {
            throw new \RuntimeException('Failed to create RS256 signature: ' . (openssl_error_string() ?: 'unknown error'));
        }

        return "{$dataToSign}." . self::base64UrlEncode($signature);
    }

    /**
     * Verify and decode an RS256 signed JWT string using a public key.
     *
     * @param string $token
     * @param \OpenSSLAsymmetricKey|string $publicKey PEM-encoded public key or certificate
     * @return array<string, mixed>
     */
    public static function decodeRs256(string $token, \OpenSSLAsymmetricKey|string $publicKey): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Malformed JWT token: must contain 3 parts');
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $headerJson = self::base64UrlDecode($headerB64);
        $header = json_decode($headerJson, true);
        if (!is_array($header) || ($header['alg'] ?? '') !== 'RS256') {
            throw new \InvalidArgumentException('Unsupported or invalid JWT algorithm (expected RS256)');
        }

        $dataToVerify = "{$headerB64}.{$payloadB64}";
        $signature = self::base64UrlDecode($signatureB64);

        $pubKey = is_string($publicKey) ? openssl_pkey_get_public($publicKey) : $publicKey;
        if ($pubKey === false) {
            throw new \InvalidArgumentException('Invalid RSA public key.');
        }

        $verifyResult = openssl_verify($dataToVerify, $signature, $pubKey, OPENSSL_ALGO_SHA256);
        if ($verifyResult !== 1) {
            throw new \RuntimeException('Invalid RS256 JWT signature.');
        }

        $payloadJson = self::base64UrlDecode($payloadB64);
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            throw new \InvalidArgumentException('Invalid JWT payload encoding');
        }

        $now = time();
        if (isset($payload['exp']) && $payload['exp'] < $now) {
            throw new \RuntimeException('JWT token has expired');
        }
        if (isset($payload['nbf']) && $payload['nbf'] > $now) {
            throw new \RuntimeException('JWT token is not yet valid');
        }

        return $payload;
    }

    /**
     * Fast boolean check whether an RS256 token is valid.
     */
    public static function verifyRs256(string $token, \OpenSSLAsymmetricKey|string $publicKey): bool
    {
        try {
            self::decodeRs256($token, $publicKey);
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
