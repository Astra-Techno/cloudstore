<?php

declare(strict_types=1);

namespace App\Modules\Notification\Service;

use App\Modules\Notification\Repository\NotificationRepository;
use Ramsey\Uuid\Uuid;

final class NotificationService
{
    public function __construct(
        private readonly NotificationRepository $notificationRepo,
    ) {
    }

    /**
     * Send an in-app notification.
     * In production, this would also dispatch to SMS/push/email channels.
     */
    public function send(int $tenantId, string $recipientType, int $recipientId, string $type, string $title, string $body, array $data = [], string $channel = 'in_app'): int
    {
        $id = $this->notificationRepo->create([
            'uuid' => Uuid::uuid4()->toString(),
            'tenant_id' => $tenantId,
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'channel' => $channel,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ]);

        $this->notificationRepo->markSent($id);

        return $id;
    }

    /**
     * Send order status notification to customer.
     */
    public function notifyOrderStatus(int $tenantId, int $customerId, string $orderNumber, string $status): void
    {
        $messages = [
            'confirmed' => ['Order Confirmed', "Your order {$orderNumber} has been confirmed."],
            'accepted' => ['Order Accepted', "Your order {$orderNumber} is being processed."],
            'preparing' => ['Preparing Your Order', "Your order {$orderNumber} is being prepared."],
            'ready' => ['Order Ready', "Your order {$orderNumber} is ready for delivery."],
            'ready_for_pickup' => ['Ready for Pickup', "Your order {$orderNumber} is ready for pickup."],
            'out_for_delivery' => ['Out for Delivery', "Your order {$orderNumber} is on its way!"],
            'delivered' => ['Order Delivered', "Your order {$orderNumber} has been delivered. Enjoy!"],
            'picked_up' => ['Order Picked Up', "Your order {$orderNumber} has been picked up."],
            'cancelled' => ['Order Cancelled', "Your order {$orderNumber} has been cancelled."],
            'refunded' => ['Refund Processed', "Refund for order {$orderNumber} has been processed."],
        ];

        $msg = $messages[$status] ?? ['Order Update', "Your order {$orderNumber} status changed to {$status}."];

        $this->send(
            $tenantId,
            'customer',
            $customerId,
            'order_' . $status,
            $msg[0],
            $msg[1],
            ['order_number' => $orderNumber, 'status' => $status],
        );
    }

    /**
     * Notify admin/tenant about new order.
     */
    public function notifyNewOrder(int $tenantId, int $adminId, string $orderNumber, int $total): void
    {
        $formattedTotal = number_format($total / 100, 2);

        $this->send(
            $tenantId,
            'admin',
            $adminId,
            'new_order',
            'New Order Received',
            "New order {$orderNumber} for ₹{$formattedTotal}",
            ['order_number' => $orderNumber, 'total' => $total],
        );
    }

    /**
     * Notify driver about new assignment.
     */
    public function notifyDriverAssignment(int $tenantId, int $driverId, string $orderNumber): void
    {
        $this->send(
            $tenantId,
            'driver',
            $driverId,
            'delivery_assigned',
            'New Delivery Assignment',
            "You have a new delivery for order {$orderNumber}.",
            ['order_number' => $orderNumber],
        );
    }
}
