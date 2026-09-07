<?php

declare(strict_types=1);

namespace Tests\Unit\Notification;

use PHPUnit\Framework\TestCase;

final class NotificationServiceTest extends TestCase
{
    public function testOrderStatusMessageMapping(): void
    {
        $messages = [
            'confirmed' => 'Order Confirmed',
            'accepted' => 'Order Accepted',
            'preparing' => 'Preparing Your Order',
            'ready' => 'Order Ready',
            'ready_for_pickup' => 'Ready for Pickup',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Order Delivered',
            'picked_up' => 'Order Picked Up',
            'cancelled' => 'Order Cancelled',
            'refunded' => 'Refund Processed',
        ];

        $this->assertCount(10, $messages);
        $this->assertEquals('Order Confirmed', $messages['confirmed']);
        $this->assertEquals('Out for Delivery', $messages['out_for_delivery']);
    }

    public function testNotificationChannels(): void
    {
        $channels = ['in_app', 'sms', 'push', 'email'];

        $this->assertContains('in_app', $channels);
        $this->assertContains('push', $channels);
    }

    public function testRecipientTypes(): void
    {
        $types = ['customer', 'admin', 'driver'];

        $this->assertCount(3, $types);
        $this->assertContains('driver', $types);
    }
}
