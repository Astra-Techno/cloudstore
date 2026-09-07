<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Modules\Auth\Service\PasswordService;
use PHPUnit\Framework\TestCase;

final class PasswordServiceTest extends TestCase
{
    private PasswordService $service;

    protected function setUp(): void
    {
        $this->service = new PasswordService();
    }

    public function testHashAndVerify(): void
    {
        $hash = $this->service->hash('SecurePassword123!');

        $this->assertTrue($this->service->verify('SecurePassword123!', $hash));
    }

    public function testWrongPasswordFails(): void
    {
        $hash = $this->service->hash('CorrectPassword');

        $this->assertFalse($this->service->verify('WrongPassword', $hash));
    }

    public function testHashIsArgon2id(): void
    {
        $hash = $this->service->hash('test');

        $this->assertStringStartsWith('$argon2id$', $hash);
    }

    public function testSamePasswordProducesDifferentHashes(): void
    {
        $hash1 = $this->service->hash('SamePassword');
        $hash2 = $this->service->hash('SamePassword');

        $this->assertNotSame($hash1, $hash2);
        // Both should still verify
        $this->assertTrue($this->service->verify('SamePassword', $hash1));
        $this->assertTrue($this->service->verify('SamePassword', $hash2));
    }
}
