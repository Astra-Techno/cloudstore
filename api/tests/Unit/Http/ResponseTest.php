<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Core\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testSuccessResponse(): void
    {
        $response = Response::success(['id' => 1]);
        $data = $response->getData();

        $this->assertSame(200, $response->getStatus());
        $this->assertTrue($data['success']);
        $this->assertSame(['id' => 1], $data['data']);
    }

    public function testErrorResponse(): void
    {
        $response = Response::error('Something went wrong', 'CUSTOM_ERROR', 400);
        $data = $response->getData();

        $this->assertSame(400, $response->getStatus());
        $this->assertFalse($data['success']);
        $this->assertSame('CUSTOM_ERROR', $data['error']['code']);
        $this->assertSame('Something went wrong', $data['error']['message']);
    }

    public function testNotFoundResponse(): void
    {
        $response = Response::notFound();

        $this->assertSame(404, $response->getStatus());
        $this->assertSame('NOT_FOUND', $response->getData()['error']['code']);
    }

    public function testValidationErrorResponse(): void
    {
        $response = Response::validationError(['email' => ['email is required.']]);
        $data = $response->getData();

        $this->assertSame(422, $response->getStatus());
        $this->assertSame('VALIDATION_ERROR', $data['error']['code']);
        $this->assertArrayHasKey('email', $data['error']['fields']);
    }
}
