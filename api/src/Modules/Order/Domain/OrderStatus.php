<?php

declare(strict_types=1);

namespace App\Modules\Order\Domain;

final class OrderStatus
{
    public const PENDING_PAYMENT = 'pending_payment';
    public const PAYMENT_PROCESSING = 'payment_processing';
    public const CONFIRMED = 'confirmed';
    public const ACCEPTED = 'accepted';
    public const PREPARING = 'preparing';
    public const READY = 'ready';
    public const READY_FOR_PICKUP = 'ready_for_pickup';
    public const OUT_FOR_DELIVERY = 'out_for_delivery';
    public const DELIVERED = 'delivered';
    public const CANCELLED = 'cancelled';
    public const REJECTED = 'rejected';
    public const REFUNDED = 'refunded';
    public const PICKED_UP = 'picked_up';

    /**
     * Allowed transitions: from => [to, to, ...]
     */
    private const TRANSITIONS = [
        self::PENDING_PAYMENT => [self::PAYMENT_PROCESSING, self::CONFIRMED, self::CANCELLED],
        self::PAYMENT_PROCESSING => [self::CONFIRMED, self::CANCELLED],
        self::CONFIRMED => [self::ACCEPTED, self::REJECTED, self::CANCELLED],
        self::ACCEPTED => [self::PREPARING, self::CANCELLED],
        self::PREPARING => [self::READY, self::READY_FOR_PICKUP, self::CANCELLED],
        self::READY => [self::OUT_FOR_DELIVERY, self::CANCELLED],
        self::READY_FOR_PICKUP => [self::PICKED_UP, self::CANCELLED],
        self::OUT_FOR_DELIVERY => [self::DELIVERED, self::CANCELLED],
        self::DELIVERED => [self::REFUNDED],
        self::PICKED_UP => [self::REFUNDED],
        self::CANCELLED => [self::REFUNDED],
        self::REJECTED => [],
        self::REFUNDED => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        $allowed = self::TRANSITIONS[$from] ?? [];

        return in_array($to, $allowed, true);
    }

    public static function getAllowedTransitions(string $from): array
    {
        return self::TRANSITIONS[$from] ?? [];
    }

    public static function isFinal(string $status): bool
    {
        return in_array($status, [self::DELIVERED, self::PICKED_UP, self::CANCELLED, self::REJECTED, self::REFUNDED], true);
    }
}
