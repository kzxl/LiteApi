<?php

declare(strict_types=1);

namespace LiteApi\Tests;

use PHPUnit\Framework\TestCase;
use LiteApi\Security\Jwt;

class JwtTest extends TestCase
{
    private const SECRET = 'my-super-secret-key-1234567890';

    public function testSignAndDecode(): void
    {
        $payload = ['sub' => 42, 'role' => 'admin'];
        $token = Jwt::sign($payload, self::SECRET, ttl: 3600);

        $this->assertNotEmpty($token);
        $this->assertTrue(Jwt::verify($token, self::SECRET));

        $decoded = Jwt::decode($token, self::SECRET);
        $this->assertSame(42, $decoded['sub']);
        $this->assertSame('admin', $decoded['role']);
        $this->assertArrayHasKey('exp', $decoded);
        $this->assertArrayHasKey('iat', $decoded);
    }

    public function testRejectsTamperedToken(): void
    {
        $payload = ['sub' => 42];
        $token = Jwt::sign($payload, self::SECRET, ttl: 3600);

        $tampered = $token . 'tampered';
        $this->assertFalse(Jwt::verify($tampered, self::SECRET));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JWT signature');
        Jwt::decode($tampered, self::SECRET);
    }

    public function testRejectsExpiredToken(): void
    {
        $payload = ['sub' => 42];
        // Expired 10 seconds ago
        $token = Jwt::sign($payload, self::SECRET, ttl: -10);

        $this->assertFalse(Jwt::verify($token, self::SECRET));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT token has expired');
        Jwt::decode($token, self::SECRET);
    }
}
