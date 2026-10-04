<?php
declare(strict_types=1);

namespace App\Modules\Order\Service;

use App\Core\Database\Connection;

final class MealSessionService
{
    public function __construct(private readonly Connection $db) {}

    public function publicSessions(int $tenantId, string $timezone, ?string $fromDate = null): array
    {
        $tz = $this->timezone($timezone);
        $start = $this->date($fromDate, $tz) ?? new \DateTimeImmutable('today', $tz);
        $sessions = $this->db->fetchAll('SELECT * FROM meal_sessions WHERE tenant_id = ? AND enabled = 1 ORDER BY sort_order, service_start, name', [$tenantId]);
        $result = [];
        for ($day = 0; $day < 7; $day++) {
            $date = $start->modify("+{$day} days");
            foreach ($sessions as $session) {
                $weekdays = json_decode((string) $session['weekdays'], true) ?: [];
                if (!in_array((int) $date->format('N'), array_map('intval', $weekdays), true)) continue;
                $item = $this->availability($session, $date, $tz);
                $productRows = $this->db->fetchAll(
                    'SELECT p.uuid, msp.price_override, msp.quantity_limit, msp.prep_minutes
                     FROM meal_session_products msp
                     JOIN products p ON p.id = msp.product_id
                     WHERE msp.meal_session_id = ? AND msp.available = 1 AND p.status = ? AND p.deleted_at IS NULL',
                    [$session['id'], 'active']
                );
                $item['product_uuids'] = array_column($productRows, 'uuid');
                $item['product_overrides'] = [];
                foreach ($productRows as $row) {
                    if ($row['price_override'] !== null || $row['quantity_limit'] !== null) {
                        $item['product_overrides'][$row['uuid']] = array_filter([
                            'price' => $row['price_override'] !== null ? (int) $row['price_override'] : null,
                            'quantity_limit' => $row['quantity_limit'] !== null ? (int) $row['quantity_limit'] : null,
                        ], fn ($v) => $v !== null);
                    }
                }
                if ($session['min_order_amount'] !== null) $item['min_order_amount'] = (int) $session['min_order_amount'];
                if ($session['delivery_fee_override'] !== null) $item['delivery_fee_override'] = (int) $session['delivery_fee_override'];
                $result[] = $item;
            }
        }
        return $result;
    }

    public function hasEnabledSessions(int $tenantId): bool
    {
        return $this->db->fetchOne('SELECT 1 FROM meal_sessions WHERE tenant_id = ? AND enabled = 1 LIMIT 1', [$tenantId]) !== null;
    }

    /** Must be called inside the checkout transaction. */
    public function lockAndCheckCapacity(int $tenantId, int $sessionId, string $serviceDate): bool
    {
        $session = $this->db->fetchOne('SELECT max_orders, enabled, paused FROM meal_sessions WHERE id = ? AND tenant_id = ? FOR UPDATE', [$sessionId, $tenantId]);
        if ($session === null || !(bool) $session['enabled'] || (bool) $session['paused']) return false;
        if ($session['max_orders'] === null) return true;
        $count = (int) ($this->db->fetchOne("SELECT COUNT(*) AS n FROM orders WHERE tenant_id = ? AND meal_session_id = ? AND service_date = ? AND status NOT IN ('cancelled','rejected','refunded')", [$tenantId, $sessionId, $serviceDate])['n'] ?? 0);
        return $count < (int) $session['max_orders'];
    }

    /** @param array<int, array<string,mixed>> $cartItems */
    public function validateSelection(int $tenantId, string $timezone, string $sessionUuid, string $serviceDate, array $cartItems): array
    {
        $session = $this->db->fetchOne('SELECT * FROM meal_sessions WHERE uuid = ? AND tenant_id = ? AND enabled = 1', [$sessionUuid, $tenantId]);
        if ($session === null) return ['error' => 'The selected meal session is unavailable.', 'code' => 'MEAL_SESSION_UNAVAILABLE'];
        if ((bool) ($session['paused'] ?? false)) return ['error' => 'This meal session is temporarily paused.', 'code' => 'MEAL_SESSION_PAUSED'];
        $tz = $this->timezone($timezone);
        $date = $this->date($serviceDate, $tz);
        if ($date === null || $date < new \DateTimeImmutable('today', $tz) || $date > new \DateTimeImmutable('+30 days', $tz)) {
            return ['error' => 'Choose a valid service date.', 'code' => 'INVALID_SERVICE_DATE'];
        }
        $availability = $this->availability($session, $date, $tz);
        if (!$availability['accepting_orders']) return ['error' => $availability['message'], 'code' => 'MEAL_SESSION_CLOSED'];

        $productIds = array_values(array_unique(array_map(fn (array $item) => (int) $item['product_id'], $cartItems)));
        if ($productIds === []) return ['error' => 'Cart is empty.', 'code' => 'CART_EMPTY'];
        $marks = implode(',', array_fill(0, count($productIds), '?'));
        $rows = $this->db->fetchAll("SELECT product_id FROM meal_session_products WHERE meal_session_id = ? AND available = 1 AND product_id IN ({$marks})", array_merge([(int) $session['id']], $productIds));
        if (count($rows) !== count($productIds)) return ['error' => 'One or more items are not available for this meal session.', 'code' => 'MEAL_SESSION_PRODUCT_UNAVAILABLE'];

        // Check session-level minimum order amount
        if ($session['min_order_amount'] !== null) {
            $subtotal = 0;
            foreach ($cartItems as $item) {
                $subtotal += (int) ($item['line_total'] ?? ((int) ($item['unit_price'] ?? $item['base_price'] ?? 0) * (int) ($item['quantity'] ?? 1)));
            }
            if ($subtotal < (int) $session['min_order_amount']) {
                return ['error' => 'Minimum order for this session is ₹' . number_format((int) $session['min_order_amount'] / 100, 2) . '.', 'code' => 'SESSION_MINIMUM_NOT_MET'];
            }
        }

        return ['session' => $session, 'availability' => $availability];
    }

    /** Kitchen production summary: grouped orders and item quantities. */
    public function productionSummary(int $tenantId, string $date): array
    {
        $sessions = $this->db->fetchAll('SELECT * FROM meal_sessions WHERE tenant_id = ? AND enabled = 1 ORDER BY sort_order, service_start', [$tenantId]);
        $result = [];
        foreach ($sessions as $session) {
            $weekdays = json_decode((string) $session['weekdays'], true) ?: [];
            $dateObj = new \DateTimeImmutable($date);
            if (!in_array((int) $dateObj->format('N'), array_map('intval', $weekdays), true)) continue;

            $orderCount = (int) ($this->db->fetchOne("SELECT COUNT(*) AS n FROM orders WHERE tenant_id = ? AND meal_session_id = ? AND service_date = ? AND status NOT IN ('cancelled','rejected','refunded')", [$tenantId, $session['id'], $date])['n'] ?? 0);

            $itemQuantities = $this->db->fetchAll(
                "SELECT p.name AS product_name, p.uuid AS product_uuid, SUM(oi.quantity) AS total_qty
                 FROM order_items oi
                 JOIN orders o ON o.id = oi.order_id
                 JOIN products p ON p.id = oi.product_id
                 WHERE o.tenant_id = ? AND o.meal_session_id = ? AND o.service_date = ?
                   AND o.status NOT IN ('cancelled','rejected','refunded')
                 GROUP BY p.id, p.name, p.uuid
                 ORDER BY total_qty DESC",
                [$tenantId, $session['id'], $date]
            );

            $result[] = [
                'session_uuid' => $session['uuid'],
                'session_name' => $session['name'],
                'service_start' => substr((string) $session['service_start'], 0, 5),
                'service_end' => substr((string) $session['service_end'], 0, 5),
                'order_count' => $orderCount,
                'capacity' => $session['max_orders'] !== null ? (int) $session['max_orders'] : null,
                'items' => array_map(fn ($row) => [
                    'product_name' => $row['product_name'],
                    'product_uuid' => $row['product_uuid'],
                    'total_quantity' => (int) $row['total_qty'],
                ], $itemQuantities),
            ];
        }
        return $result;
    }

    /** List all orders for a session+date for packing labels. */
    public function packingList(int $tenantId, string $sessionUuid, string $date): array
    {
        $session = $this->db->fetchOne('SELECT id, name FROM meal_sessions WHERE uuid = ? AND tenant_id = ?', [$sessionUuid, $tenantId]);
        if ($session === null) return [];
        return $this->db->fetchAll(
            "SELECT o.uuid, o.order_number, o.order_type, o.status,
                    COALESCE(c.name, 'Guest') AS customer_name, c.phone AS customer_phone,
                    o.address_snapshot, o.notes
             FROM orders o
             LEFT JOIN customers c ON c.id = o.customer_id
             WHERE o.tenant_id = ? AND o.meal_session_id = ? AND o.service_date = ?
               AND o.status NOT IN ('cancelled','rejected','refunded')
             ORDER BY o.created_at",
            [$tenantId, $session['id'], $date]
        );
    }

    private function availability(array $session, \DateTimeImmutable $date, \DateTimeZone $tz): array
    {
        $serviceDate = $date->format('Y-m-d');

        // Check for holiday/exception
        $exception = $this->db->fetchOne(
            'SELECT action, cutoff_override, reason FROM meal_session_exceptions WHERE meal_session_id = ? AND exception_date = ?',
            [(int) $session['id'], $serviceDate]
        );
        if ($exception !== null && $exception['action'] === 'closed') {
            return [
                'uuid' => $session['uuid'], 'name' => $session['name'], 'ordering_mode' => $session['ordering_mode'],
                'service_date' => $serviceDate, 'service_start' => substr((string) $session['service_start'], 0, 5),
                'service_end' => substr((string) $session['service_end'], 0, 5),
                'opens_at' => null, 'cutoff_at' => null,
                'accepting_orders' => false, 'full' => false, 'orders_count' => 0,
                'capacity_remaining' => null, 'message' => $exception['reason'] ?: 'This session is closed for ' . $serviceDate . '.',
                'exception' => 'closed',
            ];
        }

        $opens = new \DateTimeImmutable($serviceDate . ' ' . $session['opens_at'], $tz);
        $opens = $opens->modify(((int) $session['opens_day_offset'] >= 0 ? '+' : '') . (int) $session['opens_day_offset'] . ' days');

        // Use exception cutoff override if available
        $cutoffTime = ($exception !== null && $exception['cutoff_override'] !== null) ? $exception['cutoff_override'] : $session['cutoff_at'];
        $cutoff = new \DateTimeImmutable($serviceDate . ' ' . $cutoffTime, $tz);
        $cutoff = $cutoff->modify(((int) $session['cutoff_day_offset'] >= 0 ? '+' : '') . (int) $session['cutoff_day_offset'] . ' days');

        // Check for runtime cutoff extension
        if ($session['cutoff_extended_until'] !== null) {
            $extended = new \DateTimeImmutable($session['cutoff_extended_until'], $tz);
            if ($extended > $cutoff) $cutoff = $extended;
        }

        $serviceEnd = new \DateTimeImmutable($serviceDate . ' ' . $session['service_end'], $tz);
        if ($serviceEnd <= new \DateTimeImmutable($serviceDate . ' ' . $session['service_start'], $tz)) $serviceEnd = $serviceEnd->modify('+1 day');
        if (in_array($session['ordering_mode'], ['instant', 'both'], true) && $serviceEnd > $cutoff) $cutoff = $serviceEnd;

        $now = new \DateTimeImmutable('now', $tz);
        $count = (int) ($this->db->fetchOne("SELECT COUNT(*) AS n FROM orders WHERE tenant_id = ? AND meal_session_id = ? AND service_date = ? AND status NOT IN ('cancelled','rejected','refunded')", [(int) $session['tenant_id'], (int) $session['id'], $serviceDate])['n'] ?? 0);
        $full = $session['max_orders'] !== null && $count >= (int) $session['max_orders'];
        $paused = (bool) ($session['paused'] ?? false);
        $accepting = !$paused && $now >= $opens && $now <= $cutoff && !$full;
        $message = $paused ? 'This session is temporarily paused.'
            : ($full ? 'This session has reached capacity.'
            : ($now < $opens ? 'Ordering opens ' . $opens->format('D, j M g:i A')
            : ($now > $cutoff ? 'Ordering closed at ' . $cutoff->format('D, j M g:i A') . '. Preorder the next available date.'
            : 'Order before ' . $cutoff->format('g:i A'))));

        return [
            'uuid' => $session['uuid'], 'name' => $session['name'], 'ordering_mode' => $session['ordering_mode'],
            'service_date' => $serviceDate, 'service_start' => substr((string) $session['service_start'], 0, 5), 'service_end' => substr((string) $session['service_end'], 0, 5),
            'opens_at' => $opens->format(DATE_ATOM), 'cutoff_at' => $cutoff->format(DATE_ATOM),
            'accepting_orders' => $accepting, 'full' => $full, 'paused' => $paused, 'orders_count' => $count,
            'capacity_remaining' => $session['max_orders'] === null ? null : max(0, (int) $session['max_orders'] - $count),
            'message' => $message,
        ];
    }

    private function timezone(string $name): \DateTimeZone
    {
        try { return new \DateTimeZone($name); } catch (\Throwable) { return new \DateTimeZone('Asia/Kolkata'); }
    }

    private function date(?string $value, \DateTimeZone $tz): ?\DateTimeImmutable
    {
        if ($value === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) return null;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $tz);
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }
}
