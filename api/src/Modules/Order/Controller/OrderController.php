<?php

declare(strict_types=1);

namespace App\Modules\Order\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Modules\Auth\Repository\CustomerRepository;
use App\Modules\Cart\Repository\CartRepository;
use App\Modules\Catalog\Domain\PricingCalculator;
use App\Modules\Catalog\Repository\ProductRepository;
use App\Modules\Catalog\Repository\VariantRepository;
use App\Modules\Order\Repository\OrderRepository;
use App\Modules\Order\Service\OrderManagementService;
use App\Modules\Tenant\Domain\TenantContext;
use Ramsey\Uuid\Uuid;

final class OrderController
{
    public function __construct(
        private readonly OrderRepository $orderRepo,
        private readonly CustomerRepository $customerRepo,
        private readonly OrderManagementService $orderManagement,
        private readonly CartRepository $cartRepo,
        private readonly ProductRepository $productRepo,
        private readonly VariantRepository $variantRepo,
    ) {
    }

    public function list(Request $request, array $params): Response
    {
        $customer = $this->customerRepo->findByUuid($request->authClaims['sub']);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $customerId = (int) $customer['id'];
        $tenantId = TenantContext::id();
        $page = (int) ($request->query['page'] ?? 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $orders = $this->orderRepo->findByCustomer($customerId, $tenantId, $limit, $offset);

        return Response::success($orders);
    }

    public function show(Request $request, array $params): Response
    {
        $customer = $this->customerRepo->findByUuid($request->authClaims['sub']);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $customerId = (int) $customer['id'];
        $tenantId = TenantContext::id();

        $order = $this->orderRepo->findByUuid($params['uuid'], $tenantId);
        if ($order === null || (int) $order['customer_id'] !== $customerId) {
            return Response::notFound('Order not found.');
        }

        $items = $this->orderRepo->getItems((int) $order['id']);
        $history = $this->orderRepo->getStatusHistory((int) $order['id']);

        return Response::success([
            'order' => $order,
            'items' => $items,
            'status_history' => $history,
        ]);
    }

    public function cancel(Request $request, array $params): Response
    {
        $customer = $this->customerRepo->findByUuid($request->authClaims['sub']);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $customerId = (int) $customer['id'];
        $tenantId = TenantContext::id();

        $order = $this->orderRepo->findByUuid($params['uuid'], $tenantId);
        if ($order === null || (int) $order['customer_id'] !== $customerId) {
            return Response::notFound('Order not found.');
        }

        $data = $request->json();
        $reason = $data['reason'] ?? null;

        $result = $this->orderManagement->cancelOrder($tenantId, (int) $order['id'], $customerId, $reason);

        if (isset($result['error'])) {
            return Response::error($result['error'], $result['code'], 422);
        }

        $items = $this->orderRepo->getItems((int) $order['id']);
        $history = $this->orderRepo->getStatusHistory((int) $order['id']);

        return Response::success([
            'order' => $result,
            'items' => $items,
            'status_history' => $history,
        ]);
    }

    public function reorder(Request $request, array $params): Response
    {
        $customer = $this->customerRepo->findByUuid($request->authClaims['sub']);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $customerId = (int) $customer['id'];
        $tenantId = TenantContext::id();

        $order = $this->orderRepo->findByUuid($params['uuid'], $tenantId);
        if ($order === null || (int) $order['customer_id'] !== $customerId) {
            return Response::notFound('Order not found.');
        }

        $orderItems = $this->orderRepo->getItems((int) $order['id']);
        if (empty($orderItems)) {
            return Response::error('Original order has no items.', 'EMPTY_ORDER', 422);
        }

        // Get or create active cart, then clear it
        $cart = $this->cartRepo->findActiveByCustomer($customerId, $tenantId);
        if ($cart === null) {
            $cartId = $this->cartRepo->create([
                'uuid' => Uuid::uuid4()->toString(),
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
            ]);
        } else {
            $cartId = (int) $cart['id'];
            $this->cartRepo->clearCart($cartId);
        }

        $skipped = [];

        foreach ($orderItems as $item) {
            // Validate product still exists and is active
            $product = $this->productRepo->findById((int) $item['product_id'], $tenantId);
            if ($product === null || $product['status'] !== 'active') {
                $snapshot = json_decode($item['product_snapshot'] ?? '{}', true);
                $skipped[] = $snapshot['name'] ?? 'Unknown product';
                continue;
            }

            $variant = null;
            if ($item['variant_id'] !== null) {
                $variant = $this->variantRepo->findById((int) $item['variant_id']);
                if ($variant === null || $variant['status'] !== 'active') {
                    $snapshot = json_decode($item['product_snapshot'] ?? '{}', true);
                    $skipped[] = $snapshot['name'] ?? 'Unknown product';
                    continue;
                }
            }

            $unitPrice = PricingCalculator::getEffectivePrice(
                ['base_price' => $product['base_price'], 'sale_price' => $product['sale_price']],
                $variant ? ['price' => $variant['price']] : null,
            );

            $addonsJson = $item['addons_snapshot'] ?? null;
            $addonsPrice = (int) ($item['addons_price'] ?? 0);

            // Recalculate addons price from snapshot if available
            if ($addonsJson !== null) {
                $addons = json_decode($addonsJson, true);
                if (is_array($addons)) {
                    $addonsPrice = array_sum(array_column($addons, 'price'));
                }
            }

            $this->cartRepo->addItem([
                'cart_id' => $cartId,
                'product_id' => (int) $product['id'],
                'variant_id' => $variant ? (int) $variant['id'] : null,
                'quantity' => (int) $item['quantity'],
                'unit_price' => $unitPrice,
                'addons_json' => $addonsJson,
                'addons_price' => $addonsPrice,
                'notes' => $item['notes'] ?? null,
            ]);
        }

        $cartItems = $this->cartRepo->getItems($cartId);
        $formattedItems = array_map(function (array $row): array {
            $unitPrice = (int) $row['unit_price'];
            $addonsPrice = (int) ($row['addons_price'] ?? 0);
            $quantity = (int) $row['quantity'];
            return [
                'id' => (int) $row['id'],
                'product_uuid' => $row['product_uuid'] ?? '',
                'product_name' => $row['product_name'] ?? 'Product',
                'variant_name' => $row['variant_name'] ?? null,
                'variant_uuid' => $row['variant_uuid'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'addons_price' => $addonsPrice,
                'line_total' => ($unitPrice + $addonsPrice) * $quantity,
            ];
        }, $cartItems);

        $result = [
            'items' => $formattedItems,
            'subtotal' => array_sum(array_column($formattedItems, 'line_total')),
        ];

        if (!empty($skipped)) {
            $result['skipped_products'] = $skipped;
        }

        return Response::success($result);
    }
}
