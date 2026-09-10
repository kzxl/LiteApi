<?php

declare(strict_types=1);

namespace LiteApi\Tests;

use PHPUnit\Framework\TestCase;
use LiteApi\Security\Jwt;

class JwtRs256Test extends TestCase
{
    private string $privateKey = <<<EOD
-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQDUMuM4uY/4GS/V
J3GEY+0/T0a67Hc2QTeDd0oJODpWCnHPDXPHSoFeDgTLYRNLqm+Mx/7VdQ0xxTsv
hyFfQMCZW1uSgDKrLQBc0xMHxKLOZHtXOaIS7//FtRxo46957rO2fSnyJPkoQdvS
BNxmWUh1AbuyXv8VInKFHyno8q0mEtR00N3S6+i4D+18OfCLrymgA3AUV8orR9VE
Tig7zvtSzvAZ0d4JE63I54mo/GDj2hCvyEUJVjgZ2Rzo2jLAWnVtbgC4grr6If+4
ZYdsqjGFaHKiKDecRvskq3477x4KIAkxabuXWjEUqdh4XnMCTXNhxLAIKEqv3iBp
xN2WNlapAgMBAAECggEAKLP2J9xYRFO4fB+Lw8RTLtCrFZHkMBEEcABCwE/7yKNK
P1gqPDELbZosy0I8rEfuC8gVFqbUOBbICo7WFOpbx07h2tEiZvlD14pZXSUXwZU+
n3WbpBxLKIZcA7ag4zepWzjZAeybqmAvpTJvgGZh4qfJdCaKsWghJW3b4SYjlmiu
ga2pijoBEj8DadFkaJNVQH0cmsdtDrt7+nKiLD9zf+SyAFDIrM23FMyrPTPKF2j5
ABcIUkTEJHgTnoT+pIA8Ru4FRCZZxHepQKluYmyKtMEneFxy5IYB5TAgchg0gcKw
fLcXUJzLCk2BJRB43yN23P5/KuHGrbaNI/4Ru3UDcQKBgQD9qzwBO7BiCkPcru1R
yq4wib/1llSWrL/ZxfcjEpzeGzNfLS4wNL4dILKJJ8ZBtO+iqpmMK/orvj3b3fRL
950gjp6Qf+gp+7Zvq5M2Csu61taJztRLgORx4tCB0IcjYE5XtjScAdK+2/VoLvVT
gf/InftEMQbbkCSWexQcdzCnxQKBgQDWJhfcaOSA1Eizx/fB4g90nuFm52rrhgOj
+BDAJgwEgh3mZKvN+QXXT5DyNisUD25h3Ijd3EyAfaaycHuyvkEiTWcmDP2+Cx5o
b1SL/mk+bfomYFo65ap0Vs9TIFmxBtGq+hDFviIkBUDh3g7Ej+AQ8ZQvg3RoRYQq
t9MdVyv9lQKBgQCGKdi0tkMVt2QpBgPSrKXwWgrC812Ny5Q0s/WAAUoiVrSW9Chn
qp2afj6vN/AttGrB2AUaE/BezmGdNgkNXMsn+wZ2WpAKFG1rJo3XmWIjUZlqjY/O
1z771QB+iDHRThBhZ6bvlC9IbsGe9qceIbWM/7FzYZLE8bnINRRv8cI0GQKBgQCR
FN21hZrJbQiURDWmNPEjoUAz5zSYvAJbZAR1KVGa/Ewleq6qrLs4U79vWSN2Q74U
rsRLCqUJqBb3bR1gy2R1SNtI/CQsTHwdZM9yyLmkgf2nYj+/Bzmj9+wd9RzOwj/q
BeC3F0kp/iUm4LpAkPjMgd5TEkwbGH2iM0fj6Nr0wQKBgAO6QqOr1t4FM0pUJzLx
fUKoL8HmC5vPq8lOFvquFUYacaWIxif15vAOL1i/l9coYT95hTevlUSVy8L9/E4l
EjY8UCZc2UnWKZiKSercqFBeWL3aJRh8fOu1t49CsfNl3GwscGRr5+w/7/N+kTpz
NCM1UwmbuJi07fLwxu6p88OA
-----END PRIVATE KEY-----
EOD;

    private string $publicKey = <<<EOD
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA1DLjOLmP+Bkv1SdxhGPt
P09Guux3NkE3g3dKCTg6Vgpxzw1zx0qBXg4Ey2ETS6pvjMf+1XUNMcU7L4chX0DA
mVtbkoAyqy0AXNMTB8SizmR7VzmiEu//xbUcaOOvee6ztn0p8iT5KEHb0gTcZllI
dQG7sl7/FSJyhR8p6PKtJhLUdNDd0uvouA/tfDnwi68poANwFFfKK0fVRE4oO877
Us7wGdHeCROtyOeJqPxg49oQr8hFCVY4Gdkc6NoywFp1bW4AuIK6+iH/uGWHbKox
hWhyoig3nEb7JKt+O+8eCiAJMWm7l1oxFKnYeF5zAk1zYcSwCChKr94gacTdljZW
qQIDAQAB
-----END PUBLIC KEY-----
EOD;

    private string $otherPublicKey = <<<EOD
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA0rK0X1r4K1rE2ePvx4sU
0x1Z+T2rUq4xQk4k4r9YV5tF8g1A8k0b8p1g7A1a9s7d3a5e8f4c2b9a7f3e5d1c
a7b9c1d3e5f7a9b1c3d5e7f9a1b3c5d7e9f1a3b5c7d9e1f3a5b7c9d1e3f5a7b9
c1d3e5f7a9b1c3d5e7f9a1b3c5d7e9f1a3b5c7d9e1f3a5b7c9d1e3f5a7b9c1d3
e5f7a9b1c3d5e7f9a1b3c5d7e9f1a3b5c7d9e1f3a5b7c9d1e3f5a7b9c1d3e5f7
a9b1c3d5e7f9a1b3c5d7e9f1a3b5c7d9e1f3a5b7c9d1e3f5a7b9c1d3e5f7a9b1
c3IDAQAB
-----END PUBLIC KEY-----
EOD;

    public function testSignAndDecodeRs256(): void
    {
        $payload = [
            'sub' => 'user_123',
            'role' => 'admin',
            'email' => 'admin@kzxl.com',
        ];

        $token = Jwt::signRs256($payload, $this->privateKey, ttl: 3600);
        $this->assertNotEmpty($token);

        $decoded = Jwt::decodeRs256($token, $this->publicKey);
        $this->assertSame('user_123', $decoded['sub']);
        $this->assertSame('admin', $decoded['role']);
        $this->assertSame('admin@kzxl.com', $decoded['email']);

        $this->assertTrue(Jwt::verifyRs256($token, $this->publicKey));
    }

    public function testTamperedTokenFailsVerification(): void
    {
        $token = Jwt::signRs256(['sub' => 'user_123'], $this->privateKey);

        $parts = explode('.', $token);
        // Tamper with payload (change user id)
        $tamperedPayload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        $tamperedPayload['sub'] = 'user_hacker';
        $parts[1] = rtrim(strtr(base64_encode((string) json_encode($tamperedPayload)), '+/', '-_'), '=');
        $tamperedToken = implode('.', $parts);

        $this->assertFalse(Jwt::verifyRs256($tamperedToken, $this->publicKey));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid RS256 JWT signature.');
        Jwt::decodeRs256($tamperedToken, $this->publicKey);
    }

    public function testDifferentPublicKeyFailsVerification(): void
    {
        $token = Jwt::signRs256(['sub' => 'user_123'], $this->privateKey);

        $this->assertFalse(Jwt::verifyRs256($token, $this->otherPublicKey));
    }
}
