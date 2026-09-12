<?php

declare(strict_types=1);

namespace App\Modules\Order\Service;

use App\Core\Database\Connection;
use App\Modules\Cart\Repository\CartRepository;
use App\Modules\Catalog\Domain\PricingCalculator;
use App\Modules\Catalog\Repository\ProductRepository;
use App\Modules\Catalog\Repository\VariantRepository;
use App\Modules\Customer\Repository\AddressRepository;
use App\Modules\Delivery\Service\DeliveryFeeService;
use App\Modules\Offer\Service\DiscountCalculator;
use App\Modules\Offer\Repository\CouponRepository;
use App\Modules\Order\Domain\OrderStatus;
use App\Modules\Order\Exception\InsufficientStockException;
use App\Modules\Order\Repository\OrderRepository;
use App\Modules\Tenant\Domain\TenantContext;
use Ramsey\Uuid\Uuid;

final class CheckoutService
{
    public function __construct(
        private readonly Connection $db,
        private readonly CartRepository $cartRepo,
        private readonly ProductRepository $productRepo,
        private readonly VariantRepository $variantRepo,
        private readonly AddressRepository $addressRepo,
        private readonly OrderRepository $orderRepo,
        private readonly DeliveryFeeService $deliveryFeeService,
        private readonly DiscountCalculator $discountCalc,
        private readonly CouponRepository $couponRepo,
    ) {
    }

    /**
     * Validate cart, calculate prices, create order.
     * All prices are recalculated server-side — never trust frontend totals.
     */
    public function createOrder(int $tenantId, int $customerId, array $input): array
    {
        $tenant = TenantContext::get();
        $tenantConfig = $tenant->configuration ?? [];

        if (!$this->isStoreOpen($tenantConfig, $tenant->timezone)) {
            return ['error' => 'This store is currently closed for orders.', 'code' => 'STORE_CLOSED'];
        }
        $cart = $this->cartRepo->findActiveByCustomer($customerId, $tenantId);
        if ($cart === null) {
            return ['error' => 'No active cart found.', 'code' => 'CART_EMPTY'];
        }

        $items = $this->cartRepo->getItems((int) $cart['id']);
        if (empty($items)) {
            return ['error' => 'Cart is empty.', 'code' => 'CART_EMPTY'];
        }

        // Validate all items belong to this tenant
        foreach ($items as $item) {
            if ((int) $item['tenant_id'] !== $tenantId) {
                return ['error' => 'Cart contains products from another tenant.', 'code' => 'TENANT_MISMATCH'];
            }

            if ($item['product_status'] !== 'active') {
                return ['error' => "Product '{$item['product_name']}' is no longer available.", 'code' => 'PRODUCT_UNAVAILABLE'];
            }

            if ($item['variant_id'] !== null && $item['variant_status'] !== 'active') {
                return ['error' => "Variant '{$item['variant_name']}' is no longer available.", 'code' => 'VARIANT_UNAVAILABLE'];
            }
        }

        // Fulfilment and payment are merchant-owned choices, not client hints.
        $orderType = $input['order_type'] ?? 'delivery';
        if ($orderType === 'delivery' && !($tenantConfig['delivery_enabled'] ?? true)) {
            return ['error' => 'This store is not accepting delivery orders.', 'code' => 'DELIVERY_DISABLED'];
        }
        if ($orderType === 'pickup' && !($tenantConfig['pickup_enabled'] ?? true)) {
            return ['error' => 'This store is not accepting pickup orders.', 'code' => 'PICKUP_DISABLED'];
        }
        $paymentMethod = $input['payment_method'] ?? 'cash_on_delivery';
        $paymentSetting = $paymentMethod === 'cash_on_delivery' ? 'cod' : $paymentMethod;
        if (!in_array($paymentSetting, $tenantConfig['payment_methods'] ?? ['cod'], true)) {
            return ['error' => 'That payment method is not available for this store.', 'code' => 'PAYMENT_METHOD_DISABLED'];
        }
        $address = null;
        $addressSnapshot = '{}';
        $deliveryFee = 0;

        if ($orderType === 'delivery') {
            if (empty($input['address_uuid'])) {
                return ['error' => 'Delivery address is required.', 'code' => 'ADDRESS_REQUIRED'];
            }

            $address = $this->addressRepo->findByUuid($input['address_uuid'], $customerId, $tenantId);
            if ($address === null) {
                return ['error' => 'Address not found.', 'code' => 'ADDRESS_NOT_FOUND'];
            }

            $addressSnapshot = json_encode([
                'address_line_1' => $address['address_line_1'],
                'address_line_2' => $address['address_line_2'],
                'landmark' => $address['landmark'],
                'city' => $address['city'],
                'postal_code' => $address['postal_code'],
                'latitude' => $address['latitude'],
                'longitude' => $address['longitude'],
            ]);
        }

        // Server-side price calculation
        $subtotal = 0;
        $orderItems = [];

        foreach ($items as $item) {
            $product = ['base_price' => $item['base_price'], 'sale_price' => $item['sale_price']];
            $variant = $item['variant_id'] ? ['price' => $item['variant_price']] : null;

            $unitPrice = PricingCalculator::getEffectivePrice($product, $variant);
            $addonsPrice = (int) ($item['addons_price'] ?? 0);
            $quantity = (int) $item['quantity'];
            $lineTotal = ($unitPrice + $addonsPrice) * $quantity;
            $subtotal += $lineTotal;

            $orderItems[] = [
                'product_id' => $item['product_id'],
                'variant_id' => $item['variant_id'],
                'product_snapshot' => json_encode([
                    'name' => $item['product_name'],
                    'slug' => $item['product_slug'],
                    'pricing_mode' => $item['pricing_mode'],
                    'unit' => $item['unit'],
                ]),
                'variant_snapshot' => $item['variant_id'] ? json_encode([
                    'name' => $item['variant_name'],
                    'price' => $item['variant_price'],
                ]) : null,
                'addons_snapshot' => $item['addons_json'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'addons_price' => $addonsPrice,
                'line_total' => $lineTotal,
                'notes' => $item['notes'],
            ];
        }

        $minOrderAmount = (int) ($tenantConfig['min_order_amount'] ?? 0);
        if ($subtotal < $minOrderAmount) {
            return ['error' => 'Minimum order value is ' . number_format($minOrderAmount / 100, 2) . '.', 'code' => 'MINIMUM_ORDER_NOT_MET'];
        }

        if ($orderType === 'delivery' && $address !== null) {
            $location = $tenantConfig['delivery'] ?? [];
            $tenantLatitude = $location['latitude'] ?? null;
            $tenantLongitude = $location['longitude'] ?? null;
            $addressLatitude = $address['latitude'] ?? null;
            $addressLongitude = $address['longitude'] ?? null;

            if (!is_numeric($tenantLatitude) || !is_numeric($tenantLongitude)
                || !is_numeric($addressLatitude) || !is_numeric($addressLongitude)) {
                return [
                    'error' => 'Delivery distance cannot be calculated for this address.',
                    'code' => 'DELIVERY_LOCATION_REQUIRED',
                ];
            }

            $distanceKm = DeliveryFeeService::haversineDistance(
                (float) $tenantLatitude,
                (float) $tenantLongitude,
                (float) $addressLatitude,
                (float) $addressLongitude,
            );
            $fee = $this->deliveryFeeService->calculate($tenantId, $distanceKm, $subtotal);
            if ($fee === null) {
                return ['error' => 'Delivery is not available for this address.', 'code' => 'DELIVERY_UNAVAILABLE'];
            }

            $deliveryFee = $fee;
        }

        // Apply fixed delivery charge from settings (if no zone-based fee and order is delivery)
        if ($orderType === 'delivery' && $deliveryFee === 0) {
            $deliveryFee = (int) ($tenantConfig['delivery_charge_fixed'] ?? 0);
        }

        // Calculate service charge
        $serviceChargePercent = (float) ($tenantConfig['service_charge_percent'] ?? 0);
        $serviceCharge = $serviceChargePercent > 0
            ? (int) round($subtotal * $serviceChargePercent / 100)
            : 0;

        // Apply discounts (coupons + auto-promotions)
        $couponCode = $input['coupon_code'] ?? null;
        $discountResult = $this->discountCalc->calculate(
            $tenantId, $subtotal, $orderItems, $deliveryFee, $couponCode, $customerId
        );

        $discountAmount = $discountResult['total_discount'];
        if ($discountResult['free_delivery']) {
            $deliveryFee = 0;
        }

        $taxAmount = (int) round(max(0, $subtotal - $discountAmount) * max(0, (float) ($tenantConfig['tax_rate'] ?? 0)) / 100);
        $total = $subtotal - $discountAmount + $deliveryFee + $serviceCharge + $taxAmount;
        if ($total < 0) {
            $total = 0;
        }

        // Determine payment method and initial status
        $initialStatus = $paymentMethod === 'cash_on_delivery'
            ? OrderStatus::CONFIRMED
            : OrderStatus::PENDING_PAYMENT;

        // Create order inside transaction
        try {
            return $this->db->transaction(function () use (
                $tenantId, $customerId, $cart, $address, $addressSnapshot,
                $subtotal, $deliveryFee, $serviceCharge, $taxAmount, $total, $discountAmount, $couponCode, $discountResult,
                $orderType, $paymentMethod, $initialStatus, $orderItems, $input, $items
            ) {
                foreach ($items as $item) {
                    $stockMode = $item['variant_id'] !== null ? $item['variant_stock_mode'] : $item['stock_mode'];
                    if ($stockMode !== 'limited_stock') {
                        continue;
                    }

                    $success = $item['variant_id'] !== null
                        ? $this->variantRepo->decrementStock((int) $item['variant_id'], (int) $item['quantity'])
                        : $this->productRepo->decrementStock((int) $item['product_id'], $tenantId, (int) $item['quantity']);

                    if (!$success) {
                        throw new InsufficientStockException($item['product_name']);
                    }
                }

                $orderNumber = $this->orderRepo->generateOrderNumber($tenantId);

            $orderId = $this->orderRepo->create([
                'uuid' => Uuid::uuid4()->toString(),
                'order_number' => $orderNumber,
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
                'address_id' => $address ? (int) $address['id'] : null,
                'status' => $initialStatus,
                'order_type' => $orderType,
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'service_charge' => $serviceCharge,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'total' => $total,
                'coupon_code' => $couponCode,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentMethod === 'cash_on_delivery' ? 'cod' : 'pending',
                'notes' => $input['notes'] ?? null,
                'address_snapshot' => $addressSnapshot,
                'scheduled_at' => $input['scheduled_at'] ?? null,
            ]);

            // Record coupon usage
            if ($couponCode !== null && $discountResult['applied_coupon'] !== null) {
                $coupon = $this->couponRepo->findByCode(strtoupper($couponCode), $tenantId);
                if ($coupon !== null) {
                    $this->couponRepo->recordUsage(
                        (int) $coupon['id'], $customerId, $orderId, $discountResult['coupon_discount']
                    );
                }
            }

            foreach ($orderItems as $item) {
                $item['order_id'] = $orderId;
                $this->orderRepo->addItem($item);
            }

            $this->orderRepo->addStatusHistory($orderId, null, $initialStatus, 'customer', $customerId);

            // Mark cart as completed
            $this->cartRepo->markCompleted((int) $cart['id']);

            $order = $this->orderRepo->findById($orderId, $tenantId);

                return [
                    'order' => $order,
                    'items' => $this->orderRepo->getItems($orderId),
                ];
            });
        } catch (InsufficientStockException $exception) {
            return [
                'error' => "Insufficient stock for '{$exception->getProductName()}'.",
                'code' => 'INSUFFICIENT_STOCK',
            ];
        }
    }

    /**
     * Business hours are optional.  With no configured schedule the merchant is
     * treated as open, preserving the behaviour of existing stores.
     */
    private function isStoreOpen(array $config, string $timezone): bool
    {
        $hours = $config['business_hours'] ?? null;
        if (!is_array($hours) || $hours === []) {
            return true;
        }

        try {
            $now = new \DateTimeImmutable('now', new \DateTimeZone($timezone));
        } catch (\Exception) {
            $now = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata'));
        }

        $dayKeys = [strtolower($now->format('l')), strtolower(substr($now->format('l'), 0, 3))];
        $schedule = null;
        foreach ($dayKeys as $day) {
            if (isset($hours[$day]) && is_array($hours[$day])) {
                $schedule = $hours[$day];
                break;
            }
        }
        if ($schedule === null) {
            return true;
        }
        if (($schedule['open'] ?? true) === false) {
            return false;
        }

        $opensAt = $schedule['open_time'] ?? $schedule['start'] ?? null;
        $closesAt = $schedule['close_time'] ?? $schedule['end'] ?? null;
        if (!is_string($opensAt) || !is_string($closesAt)) {
            return true;
        }

        $current = $now->format('H:i');
        // Support both same-day and overnight service windows.
        return $opensAt <= $closesAt
            ? $current >= $opensAt && $current <= $closesAt
            : $current >= $opensAt || $current <= $closesAt;
    }
}
