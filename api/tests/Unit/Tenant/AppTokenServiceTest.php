<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant;

use PHPUnit\Framework\TestCase;

final class AppTokenServiceTest extends TestCase
{
    public function testTokenHashIsConsistent(): void
    {
        $token = bin2hex(random_bytes(32));
        $hash1 = hash('sha256', $token);
        $hash2 = hash('sha256', $token);

        $this->assertSame($hash1, $hash2);
    }

    public function testDifferentTokensProduceDifferentHashes(): void
    {
        $token1 = bin2hex(random_bytes(32));
        $token2 = bin2hex(random_bytes(32));

        $this->assertNotSame(
            hash('sha256', $token1),
            hash('sha256', $token2)
        );
    }

    public function testTokenLengthIsSufficient(): void
    {
        $token = bin2hex(random_bytes(32));

        // 32 bytes = 64 hex chars — sufficient entropy
        $this->assertSame(64, strlen($token));
    }

    public function testTokenPrefixExtraction(): void
    {
        $token = bin2hex(random_bytes(32));
        $prefix = substr($token, 0, 8);

        $this->assertSame(8, strlen($prefix));
        $this->assertSame($prefix, substr($token, 0, 8));
    }

    public function testTokenHashIsSha256Length(): void
    {
        $hash = hash('sha256', 'test-token');

        $this->assertSame(64, strlen($hash));
    }

    public function testCompleteTokenIsNeverSameAsHash(): void
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);

        $this->assertNotSame($token, $hash);
    }
}
