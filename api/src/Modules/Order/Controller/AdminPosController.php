<?php

declare(strict_types=1);

namespace App\Modules\Order\Controller;

use App\Core\Database\Connection;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Modules\Auth\Repository\CustomerRepository;
use App\Modules\Catalog\Repository\ProductRepository;
use App\Modules\Catalog\Repository\VariantRepository;
use App\Modules\Order\Domain\OrderStatus;
use App\Modules\Order\Repository\OrderRepository;
use Ramsey\Uuid\Uuid;

/** A counter checkout is deliberately admin-only: prices and stock are resolved server-side. */
final class AdminPosController
{
    public function __construct(
        private readonly Connection $db,
        private readonly ProductRepository $productRepo,
        private readonly VariantRepository $variantRepo,
        private readonly CustomerRepository $customerRepo,
        private readonly OrderRepository $orderRepo,
    ) {
    }

    public function checkout(Request $request, array $params): Response
    {
        $data = $request->json();
        $tenantId = (int) $request->authClaims['tenant_id'];
        $items = $data['items'] ?? [];
        $paymentMethod = $data['payment_method'] ?? 'cash';
        $orderType = $data['order_type'] ?? 'pickup';

        if (!is_array($items) || $items === []) {
            return Response::error('Add at least one product to the sale.', 'POS_EMPTY_CART', 422);
        }
        if (!in_array($paymentMethod, ['cash', 'upi', 'card'], true)) {
            return Response::validationError(['payment_method' => ['Choose cash, UPI, or card.']]);
        }
        if (!in_array($orderType, ['pickup', 'delivery'], true)) {
            return Response::validationError(['order_type' => ['Choose pickup or delivery.']]);
        }
        if ($orderType === 'delivery') {
            return Response::error('Counter delivery requires a customer address. Use the customer checkout flow.', 'POS_DELIVERY_UNSUPPORTED', 422);
        }

        try {
            $result = $this->db->transaction(function () use ($data, $items, $tenantId, $paymentMethod, $orderType) {
                $phone = trim((string) ($data['customer_phone'] ?? ''));
                $customer = $phone !== '' ? $this->customerRepo->findByPhone($tenantId, $phone) : null;
                if ($customer === null) {
                    $customerId = $this->customerRepo->create([
                        'uuid' => Uuid::uuid4()->toString(),
                        'tenant_id' => $tenantId,
                        'name' => trim((string) ($data['customer_name'] ?? '')) ?: 'Walk-in customer',
                        'phone' => $phone !== '' ? $phone : null,
                        'status' => 'active',
                    ]);
                    $customer = $this->customerRepo->findById($customerId);
                }

                $subtotal = 0;
                $orderItems = [];
                foreach ($items as $line) {
                    $product = $this->productRepo->findByUuid((string) ($line['product_uuid'] ?? ''), $tenantId);
                    $quantity = max(1, min(99, (int) ($line['quantity'] ?? 1)));
                    if ($product === null || $product['status'] !== 'active') {
                        throw new \RuntimeException('One of the selected products is unavailable.');
                    }
                    if ($product['stock_mode'] === 'limited_stock' && !$this->productRepo->decrementStock((int) $product['id'], $tenantId, $quantity)) {
                        throw new \RuntimeException("Insufficient stock for {$product['name']}.");
                    }

                    // Resolve variant if provided (for weight-based products)
                    $variant = null;
                    $variantId = null;
                    $variantSnapshot = null;
                    if (!empty($line['variant_uuid'])) {
                        $variant = $this->variantRepo->findByUuid($line['variant_uuid']);
                        if ($variant !== null && (int) $variant['product_id'] === (int) $product['id'] && $variant['status'] === 'active') {
                            $variantId = (int) $variant['id'];
                            $variantSnapshot = json_encode(['name' => $variant['name'], 'price' => $variant['price'], 'weight_grams' => $variant['weight_grams']]);
                        }
                    }

                    $unitPrice = $variant ? (int) $variant['price'] : (int) ($product['sale_price'] ?? $product['base_price']);
                    $lineTotal = $unitPrice * $quantity;
                    $subtotal += $lineTotal;
                    $orderItems[] = [
                        'product_id' => (int) $product['id'],
                        'variant_id' => $variantId,
                        'product_snapshot' => json_encode(['name' => $product['name'], 'slug' => $product['slug'], 'pricing_mode' => $product['pricing_mode'], 'unit' => $product['unit']]),
                        'variant_snapshot' => $variantSnapshot,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                    ];
                }

                $orderId = $this->orderRepo->create([
                    'uuid' => Uuid::uuid4()->toString(),
                    'order_number' => $this->orderRepo->generateOrderNumber($tenantId),
                    'tenant_id' => $tenantId,
                    'customer_id' => (int) $customer['id'],
                    'status' => OrderStatus::CONFIRMED,
                    'order_type' => $orderType,
                    'subtotal' => $subtotal,
                    'total' => $subtotal,
                    'payment_method' => 'pos_' . $paymentMethod,
                    'payment_status' => 'paid',
                    'notes' => trim((string) ($data['notes'] ?? '')) ?: null,
                    'address_snapshot' => '{}',
                ]);
                foreach ($orderItems as $item) {
                    $item['order_id'] = $orderId;
                    $this->orderRepo->addItem($item);
                }
                $this->orderRepo->addStatusHistory($orderId, null, OrderStatus::CONFIRMED, 'admin', null, 'Counter sale');
                $order = $this->orderRepo->findById($orderId, $tenantId);
                return ['order' => $order, 'items' => $this->orderRepo->getItems($orderId)];
            });
            return Response::success($result, status: 201);
        } catch (\RuntimeException $e) {
            return Response::error($e->getMessage(), 'POS_CHECKOUT_FAILED', 422);
        }
    }
}
