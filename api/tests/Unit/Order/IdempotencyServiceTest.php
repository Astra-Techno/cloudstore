<?php

declare(strict_types=1);

namespace Tests\Unit\Order;

use App\Modules\Order\Service\IdempotencyService;
use PHPUnit\Framework\TestCase;

final class IdempotencyServiceTest extends TestCase
{
    public function testHashIsStableAcrossObjectKeyOrder(): void
    {
        $first = ['payment_method' => 'online', 'nested' => ['b' => 2, 'a' => 1]];
        $second = ['nested' => ['a' => 1, 'b' => 2], 'payment_method' => 'online'];

        self::assertSame(IdempotencyService::hashRequest($first), IdempotencyService::hashRequest($second));
    }

    public function testHashPreservesListOrder(): void
    {
        self::assertNotSame(
            IdempotencyService::hashRequest(['items' => [1, 2]]),
            IdempotencyService::hashRequest(['items' => [2, 1]])
        );
    }
}
