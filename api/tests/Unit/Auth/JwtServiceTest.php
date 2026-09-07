<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Modules\Auth\Service\JwtService;
use PHPUnit\Framework\TestCase;

final class JwtServiceTest extends TestCase
{
    private JwtService $jwt;

    protected function setUp(): void
    {
        $this->jwt = new JwtService('test-secret-key-minimum-length-32chars!', 3600);
    }

    public function testIssueAndVerify(): void
    {
        $token = $this->jwt->issue([
            'sub' => 'user-uuid-123',
            'type' => 'admin',
            'role' => 'tenant_owner',
        ]);

        $this->assertNotEmpty($token);

        $claims = $this->jwt->verify($token);

        $this->assertNotNull($claims);
        $this->assertSame('user-uuid-123', $claims['sub']);
        $this->assertSame('admin', $claims['type']);
        $this->assertSame('tenant_owner', $claims['role']);
    }

    public function testVerifyInvalidTokenReturnsNull(): void
    {
        $claims = $this->jwt->verify('invalid.token.here');

        $this->assertNull($claims);
    }

    public function testVerifyTamperedTokenReturnsNull(): void
    {
        $token = $this->jwt->issue(['sub' => 'test']);
        $tampered = $token . 'x';

        $claims = $this->jwt->verify($tampered);

        $this->assertNull($claims);
    }

    public function testDifferentSecretCannotVerify(): void
    {
        $token = $this->jwt->issue(['sub' => 'test']);
        $otherJwt = new JwtService('different-secret-key-also-32chars!', 3600);

        $claims = $otherJwt->verify($token);

        $this->assertNull($claims);
    }

    public function testExpiredTokenReturnsNull(): void
    {
        $shortLivedJwt = new JwtService('test-secret-key-minimum-length-32chars!', -1);
        $token = $shortLivedJwt->issue(['sub' => 'test']);

        $claims = $this->jwt->verify($token);

        $this->assertNull($claims);
    }

    public function testTokenContainsJti(): void
    {
        $token = $this->jwt->issue(['sub' => 'test']);
        $claims = $this->jwt->verify($token);

        $this->assertArrayHasKey('jti', $claims);
        $this->assertSame(32, strlen($claims['jti']));
    }

    public function testEachTokenHasUniqueJti(): void
    {
        $token1 = $this->jwt->issue(['sub' => 'test']);
        $token2 = $this->jwt->issue(['sub' => 'test']);

        $claims1 = $this->jwt->verify($token1);
        $claims2 = $this->jwt->verify($token2);

        $this->assertNotSame($claims1['jti'], $claims2['jti']);
    }
}
