<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    public function testRequiredFieldPasses(): void
    {
        $valid = $this->validator->validate(['name' => 'Test'], ['name' => ['required']]);

        $this->assertTrue($valid);
        $this->assertEmpty($this->validator->getErrors());
    }

    public function testRequiredFieldFails(): void
    {
        $valid = $this->validator->validate([], ['name' => ['required']]);

        $this->assertFalse($valid);
        $this->assertArrayHasKey('name', $this->validator->getErrors());
    }

    public function testEmailValidation(): void
    {
        $valid = $this->validator->validate(
            ['email' => 'not-an-email'],
            ['email' => ['required', 'email']]
        );

        $this->assertFalse($valid);
    }

    public function testMinLengthValidation(): void
    {
        $valid = $this->validator->validate(
            ['password' => 'ab'],
            ['password' => ['required', 'min:6']]
        );

        $this->assertFalse($valid);
    }

    public function testInValidation(): void
    {
        $valid = $this->validator->validate(
            ['status' => 'invalid'],
            ['status' => ['required', 'in:active,draft,suspended']]
        );

        $this->assertFalse($valid);
    }

    public function testInValidationPasses(): void
    {
        $valid = $this->validator->validate(
            ['status' => 'active'],
            ['status' => ['required', 'in:active,draft,suspended']]
        );

        $this->assertTrue($valid);
    }
}
