<?php

declare(strict_types=1);

namespace App\Modules\Order\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Modules\Auth\Repository\CustomerRepository;
use App\Modules\Order\Repository\OrderRepository;
use App\Modules\Delivery\Repository\DriverAssignmentRepository;
use App\Modules\Delivery\Service\DeliveryFeeService;
use App\Modules\Tenant\Domain\TenantContext;

final class OrderStreamController
{
    public function __construct(
        private readonly OrderRepository $orderRepo,
        private readonly CustomerRepository $customerRepo,
        private readonly DriverAssignmentRepository $assignmentRepo,
    ) {
    }

    /**
     * GET /customer/orders/{uuid}/stream
     * Server-Sent Events stream for real-time order status updates.
     */
    public function stream(Request $request, array $params): Response
    {
        $customer = $this->customerRepo->findByUuid($request->authClaims['sub']);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $tenantId = TenantContext::id();
        $order = $this->orderRepo->findByUuid($params['uuid'], $tenantId);
        if ($order === null || (int) $order['customer_id'] !== (int) $customer['id']) {
            return Response::notFound('Order not found.');
        }

        $orderId = (int) $order['id'];

        // SSE headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        header('Access-Control-Allow-Origin: *');

        // Flush any output buffers
        while (ob_get_level()) {
            ob_end_flush();
        }

        $lastStatus = $order['status'];
        $lastDriverLat = null;
        $maxDuration = 300; // 5 minutes max
        $start = time();

        // Send initial state immediately
        $this->sendEvent('status', [
            'status' => $order['status'],
            'updated_at' => $order['updated_at'],
        ]);

        $driver = $this->getDriverLocation($orderId, $order['address_snapshot'] ?? null);
        if ($driver !== null) {
            $this->sendEvent('driver_location', $driver);
            $lastDriverLat = $driver['latitude'] ?? null;
        }

        while ((time() - $start) < $maxDuration) {
            if (connection_aborted()) {
                break;
            }

            sleep(3);

            // Check for order status change
            $current = $this->orderRepo->findById($orderId, $tenantId);
            if ($current === null) {
                break;
            }

            if ($current['status'] !== $lastStatus) {
                $lastStatus = $current['status'];
                $this->sendEvent('status', [
                    'status' => $current['status'],
                    'updated_at' => $current['updated_at'],
                ]);

                // Terminal states - close stream
                if (in_array($current['status'], ['delivered', 'picked_up', 'cancelled', 'rejected', 'refunded'], true)) {
                    $this->sendEvent('close', ['reason' => 'order_completed']);
                    break;
                }
            }

            // Check driver location updates
            $driver = $this->getDriverLocation($orderId, $current['address_snapshot'] ?? null);
            if ($driver !== null) {
                $newLat = $driver['latitude'] ?? null;
                if ($newLat !== $lastDriverLat) {
                    $this->sendEvent('driver_location', $driver);
                    $lastDriverLat = $newLat;
                }
            }

            // Heartbeat
            echo ": heartbeat\n\n";
            flush();
        }

        $this->sendEvent('close', ['reason' => 'timeout']);
        exit;
    }

    /**
     * GET /admin/orders/stream
     * SSE stream for new orders (admin real-time board).
     */
    public function adminStream(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        header('Access-Control-Allow-Origin: *');

        while (ob_get_level()) {
            ob_end_flush();
        }

        $lastOrderId = (int) ($this->orderRepo->getLatestOrderId($tenantId) ?? 0);
        $maxDuration = 300;
        $start = time();

        while ((time() - $start) < $maxDuration) {
            if (connection_aborted()) {
                break;
            }

            sleep(5);

            $newOrders = $this->orderRepo->getOrdersSince($tenantId, $lastOrderId);
            foreach ($newOrders as $order) {
                $this->sendEvent('new_order', [
                    'id' => (int) $order['id'],
                    'uuid' => $order['uuid'],
                    'order_number' => $order['order_number'],
                    'status' => $order['status'],
                    'total' => (int) $order['total'],
                    'order_type' => $order['order_type'],
                    'created_at' => $order['created_at'],
                ]);
                $lastOrderId = max($lastOrderId, (int) $order['id']);
            }

            echo ": heartbeat\n\n";
            flush();
        }

        exit;
    }

    private function sendEvent(string $event, array $data): void
    {
        echo "event: {$event}\ndata: " . json_encode($data) . "\n\n";
        flush();
    }

    private function getDriverLocation(int $orderId, ?string $addressSnapshot): ?array
    {
        $assignment = $this->assignmentRepo->findByOrderId($orderId);
        if ($assignment === null || !is_numeric($assignment['last_location_lat'] ?? null)) {
            return null;
        }

        $driver = [
            'name' => $assignment['driver_name'] ?? 'Delivery partner',
            'phone' => $assignment['driver_phone'] ?? null,
            'latitude' => (float) $assignment['last_location_lat'],
            'longitude' => (float) $assignment['last_location_lng'],
            'location_updated_at' => $assignment['last_location_at'] ?? null,
        ];

        $destination = json_decode((string) $addressSnapshot, true);
        if (is_array($destination)
            && is_numeric($destination['latitude'] ?? null)
            && is_numeric($destination['longitude'] ?? null)) {
            $distanceKm = DeliveryFeeService::haversineDistance(
                $driver['latitude'],
                $driver['longitude'],
                (float) $destination['latitude'],
                (float) $destination['longitude'],
            );
            $driver['distance_km'] = round($distanceKm, 1);
            $driver['eta_minutes'] = max(1, (int) ceil(($distanceKm * 1.25) / 22 * 60));
        }

        return $driver;
    }
}
