<?php

declare(strict_types=1);

namespace App\Modules\Offer\Service;

use App\Modules\Offer\Repository\CouponRepository;
use Ramsey\Uuid\Uuid;

final class CouponService
{
    public function __construct(private readonly CouponRepository $repo)
    {
    }

    public function create(int $tenantId, array $data): array
    {
        $code = strtoupper(trim($data['code']));

        $existing = $this->repo->findByCode($code, $tenantId);
        if ($existing !== null) {
            return ['error' => 'Coupon code already exists.', 'code' => 'DUPLICATE_CODE'];
        }

        $id = $this->repo->create([
            'uuid' => Uuid::uuid4()->toString(),
            'tenant_id' => $tenantId,
            'code' => $code,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'discount_type' => $data['discount_type'] ?? 'percentage',
            'discount_value' => (int) $data['discount_value'],
            'min_order_amount' => (int) ($data['min_order_amount'] ?? 0),
            'max_discount_amount' => isset($data['max_discount_amount']) ? (int) $data['max_discount_amount'] : null,
            'usage_limit' => isset($data['usage_limit']) ? (int) $data['usage_limit'] : null,
            'per_customer_limit' => (int) ($data['per_customer_limit'] ?? 1),
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => (int) ($data['is_active'] ?? 1),
            'applies_to' => $data['applies_to'] ?? 'all',
            'applies_to_ids' => isset($data['applies_to_ids']) ? json_encode($data['applies_to_ids']) : null,
        ]);

        return $this->repo->findById($id, $tenantId);
    }

    public function update(int $tenantId, string $uuid, array $data): array
    {
        $coupon = $this->repo->findByUuid($uuid, $tenantId);
        if ($coupon === null) {
            return ['error' => 'Coupon not found.', 'code' => 'NOT_FOUND'];
        }

        $fields = [];
        $allowed = ['title', 'description', 'discount_type', 'discount_value', 'min_order_amount',
                     'max_discount_amount', 'usage_limit', 'per_customer_limit', 'starts_at', 'expires_at',
                     'is_active', 'applies_to', 'applies_to_ids'];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $value = $data[$key];
                if ($key === 'applies_to_ids' && is_array($value)) {
                    $value = json_encode($value);
                }
                if (in_array($key, ['discount_value', 'min_order_amount', 'max_discount_amount', 'usage_limit', 'per_customer_limit', 'is_active'], true)) {
                    $value = $value !== null ? (int) $value : null;
                }
                $fields[$key] = $value;
            }
        }

        if (array_key_exists('code', $data)) {
            $newCode = strtoupper(trim($data['code']));
            if ($newCode !== $coupon['code']) {
                $existing = $this->repo->findByCode($newCode, $tenantId);
                if ($existing !== null) {
                    return ['error' => 'Coupon code already exists.', 'code' => 'DUPLICATE_CODE'];
                }
                $fields['code'] = $newCode;
            }
        }

        $this->repo->update((int) $coupon['id'], $tenantId, $fields);

        return $this->repo->findByUuid($uuid, $tenantId);
    }

    public function delete(int $tenantId, string $uuid): array
    {
        $coupon = $this->repo->findByUuid($uuid, $tenantId);
        if ($coupon === null) {
            return ['error' => 'Coupon not found.', 'code' => 'NOT_FOUND'];
        }

        $this->repo->delete((int) $coupon['id'], $tenantId);
        return ['deleted' => true];
    }

    /**
     * Validate a coupon code for a customer's cart.
     * Returns coupon data + calculated discount, or error.
     */
    public function validate(int $tenantId, string $code, int $customerId, int $subtotal, array $cartItems = []): array
    {
        $coupon = $this->repo->findByCode(strtoupper(trim($code)), $tenantId);

        if ($coupon === null) {
            return ['error' => 'Invalid coupon code.', 'code' => 'INVALID_CODE'];
        }

        if (!$coupon['is_active']) {
            return ['error' => 'This coupon is no longer active.', 'code' => 'COUPON_INACTIVE'];
        }

        $now = date('Y-m-d H:i:s');
        if ($coupon['starts_at'] !== null && $coupon['starts_at'] > $now) {
            return ['error' => 'This coupon is not yet active.', 'code' => 'COUPON_NOT_STARTED'];
        }

        if ($coupon['expires_at'] !== null && $coupon['expires_at'] < $now) {
            return ['error' => 'This coupon has expired.', 'code' => 'COUPON_EXPIRED'];
        }

        if ($coupon['usage_limit'] !== null && $coupon['used_count'] >= $coupon['usage_limit']) {
            return ['error' => 'This coupon has reached its usage limit.', 'code' => 'COUPON_EXHAUSTED'];
        }

        $customerUsage = $this->repo->getCustomerUsageCount((int) $coupon['id'], $customerId);
        if ($customerUsage >= $coupon['per_customer_limit']) {
            return ['error' => 'You have already used this coupon.', 'code' => 'COUPON_ALREADY_USED'];
        }

        if ($subtotal < (int) $coupon['min_order_amount']) {
            $minAmount = number_format((int) $coupon['min_order_amount'] / 100, 2);
            return ['error' => "Minimum order amount is {$minAmount}.", 'code' => 'MIN_ORDER_NOT_MET'];
        }

        $discount = $this->calculateDiscount($coupon, $subtotal, $cartItems);

        return [
            'coupon' => $coupon,
            'discount_amount' => $discount,
        ];
    }

    public function calculateDiscount(array $coupon, int $subtotal, array $cartItems = []): int
    {
        $applicableAmount = $subtotal;

        // Filter applicable amount by category/product if needed
        if ($coupon['applies_to'] !== 'all' && !empty($coupon['applies_to_ids'])) {
            $targetIds = json_decode($coupon['applies_to_ids'], true) ?? [];
            if (!empty($targetIds) && !empty($cartItems)) {
                $applicableAmount = 0;
                foreach ($cartItems as $item) {
                    $matchField = $coupon['applies_to'] === 'category' ? 'category_uuid' : 'product_uuid';
                    if (in_array($item[$matchField] ?? '', $targetIds, true)) {
                        $applicableAmount += (int) $item['line_total'];
                    }
                }
            }
        }

        if ($applicableAmount <= 0) {
            return 0;
        }

        $discount = match ($coupon['discount_type']) {
            'percentage' => (int) floor($applicableAmount * (int) $coupon['discount_value'] / 10000),
            'fixed' => min((int) $coupon['discount_value'], $applicableAmount),
            'free_delivery' => 0, // handled separately in checkout
            default => 0,
        };

        // Apply max discount cap
        if ($coupon['max_discount_amount'] !== null && $discount > (int) $coupon['max_discount_amount']) {
            $discount = (int) $coupon['max_discount_amount'];
        }

        return $discount;
    }
}
