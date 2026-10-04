<?php
declare(strict_types=1);

namespace App\Modules\Order\Controller;

use App\Core\Database\Connection;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Modules\Order\Service\MealSessionService;
use App\Modules\Tenant\Domain\TenantContext;
use Ramsey\Uuid\Uuid;

final class MealSessionController
{
    public function __construct(private readonly Connection $db, private readonly MealSessionService $service) {}

    // ── Public ──────────────────────────────────────────────
    public function publicList(Request $request, array $params): Response
    {
        $tenant = TenantContext::get();
        return Response::success($this->service->publicSessions($tenant->id, $tenant->timezone, $request->input('from_date')));
    }

    // ── Admin CRUD ─────────────────────────────────────────
    public function adminList(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $sessions = $this->db->fetchAll('SELECT * FROM meal_sessions WHERE tenant_id = ? ORDER BY sort_order, service_start, name', [$tenantId]);
        foreach ($sessions as &$session) {
            $session['weekdays'] = json_decode((string) $session['weekdays'], true) ?: [];
            $session['product_uuids'] = array_column($this->db->fetchAll('SELECT p.uuid FROM meal_session_products m JOIN products p ON p.id = m.product_id WHERE m.meal_session_id = ? ORDER BY p.name', [$session['id']]), 'uuid');
            $session['exceptions'] = $this->db->fetchAll('SELECT exception_date, action, cutoff_override, reason FROM meal_session_exceptions WHERE meal_session_id = ? ORDER BY exception_date', [$session['id']]);
        }
        return Response::success($sessions);
    }

    public function create(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $data = $request->json();
        $error = $this->validate($data);
        $productError = $this->validateProducts($tenantId, $data['product_uuids'] ?? []);
        if ($productError) $error['product_uuids'][] = $productError;
        if ($error) return Response::validationError($error);
        $uuid = Uuid::uuid4()->toString();
        $this->db->transaction(function () use ($tenantId, $data, $uuid) {
            $this->db->execute(
                'INSERT INTO meal_sessions (uuid, tenant_id, name, ordering_mode, weekdays, service_start, service_end, opens_day_offset, opens_at, cutoff_day_offset, cutoff_at, max_orders, delivery_fee_override, min_order_amount, enabled, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [
                    $uuid, $tenantId, trim($data['name']), $data['ordering_mode'],
                    json_encode(array_values(array_unique(array_map('intval', $data['weekdays'])))),
                    $data['service_start'], $data['service_end'],
                    (int) $data['opens_day_offset'], $data['opens_at'],
                    (int) $data['cutoff_day_offset'], $data['cutoff_at'],
                    empty($data['max_orders']) ? null : (int) $data['max_orders'],
                    isset($data['delivery_fee_override']) && $data['delivery_fee_override'] !== null && $data['delivery_fee_override'] !== '' ? (int) $data['delivery_fee_override'] : null,
                    isset($data['min_order_amount']) && $data['min_order_amount'] !== null && $data['min_order_amount'] !== '' ? (int) $data['min_order_amount'] : null,
                    ($data['enabled'] ?? true) ? 1 : 0,
                    (int) ($data['sort_order'] ?? 0),
                ]
            );
            $sessionId = (int) $this->db->lastInsertId();
            $this->syncProducts($sessionId, $tenantId, $data['product_uuids'] ?? [], $data['product_overrides'] ?? []);
        });
        return Response::success(['uuid' => $uuid], status: 201);
    }

    public function update(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $data = $request->json();
        $error = $this->validate($data);
        $productError = $this->validateProducts($tenantId, $data['product_uuids'] ?? []);
        if ($productError) $error['product_uuids'][] = $productError;
        if ($error) return Response::validationError($error);
        $session = $this->db->fetchOne('SELECT id FROM meal_sessions WHERE uuid = ? AND tenant_id = ?', [$params['uuid'], $tenantId]);
        if (!$session) return Response::notFound('Meal session not found.');
        $this->db->transaction(function () use ($session, $tenantId, $data) {
            $this->db->execute(
                'UPDATE meal_sessions SET name=?, ordering_mode=?, weekdays=?, service_start=?, service_end=?, opens_day_offset=?, opens_at=?, cutoff_day_offset=?, cutoff_at=?, max_orders=?, delivery_fee_override=?, min_order_amount=?, enabled=?, sort_order=? WHERE id=?',
                [
                    trim($data['name']), $data['ordering_mode'],
                    json_encode(array_values(array_unique(array_map('intval', $data['weekdays'])))),
                    $data['service_start'], $data['service_end'],
                    (int) $data['opens_day_offset'], $data['opens_at'],
                    (int) $data['cutoff_day_offset'], $data['cutoff_at'],
                    empty($data['max_orders']) ? null : (int) $data['max_orders'],
                    isset($data['delivery_fee_override']) && $data['delivery_fee_override'] !== null && $data['delivery_fee_override'] !== '' ? (int) $data['delivery_fee_override'] : null,
                    isset($data['min_order_amount']) && $data['min_order_amount'] !== null && $data['min_order_amount'] !== '' ? (int) $data['min_order_amount'] : null,
                    ($data['enabled'] ?? true) ? 1 : 0,
                    (int) ($data['sort_order'] ?? 0),
                    (int) $session['id'],
                ]
            );
            $this->syncProducts((int) $session['id'], $tenantId, $data['product_uuids'] ?? [], $data['product_overrides'] ?? []);
        });
        return Response::success(['updated' => true]);
    }

    public function delete(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $session = $this->db->fetchOne('SELECT id FROM meal_sessions WHERE uuid = ? AND tenant_id = ?', [$params['uuid'], $tenantId]);
        if (!$session) return Response::notFound('Meal session not found.');
        $used = $this->db->fetchOne('SELECT 1 FROM orders WHERE meal_session_id = ? LIMIT 1', [$session['id']]);
        if ($used) $this->db->execute('UPDATE meal_sessions SET enabled = 0 WHERE id = ?', [$session['id']]);
        else $this->db->execute('DELETE FROM meal_sessions WHERE id = ?', [$session['id']]);
        return Response::success(['deleted' => !$used, 'disabled' => (bool) $used]);
    }

    // ── Quick Actions ──────────────────────────────────────
    public function quickAction(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $session = $this->db->fetchOne('SELECT * FROM meal_sessions WHERE uuid = ? AND tenant_id = ?', [$params['uuid'], $tenantId]);
        if (!$session) return Response::notFound('Meal session not found.');

        $data = $request->json();
        $action = $data['action'] ?? '';

        return match ($action) {
            'pause' => $this->actionPause($session),
            'resume' => $this->actionResume($session),
            'extend_cutoff' => $this->actionExtendCutoff($session, $data),
            'close_early' => $this->actionCloseEarly($session),
            'adjust_capacity' => $this->actionAdjustCapacity($session, $data),
            'mark_sold_out' => $this->actionMarkSoldOut($session, $data),
            default => Response::error('Unknown action.', 'INVALID_ACTION', 422),
        };
    }

    private function actionPause(array $session): Response
    {
        $this->db->execute('UPDATE meal_sessions SET paused = 1 WHERE id = ?', [$session['id']]);
        return Response::success(['paused' => true]);
    }

    private function actionResume(array $session): Response
    {
        $this->db->execute('UPDATE meal_sessions SET paused = 0 WHERE id = ?', [$session['id']]);
        return Response::success(['paused' => false]);
    }

    private function actionExtendCutoff(array $session, array $data): Response
    {
        $minutes = (int) ($data['minutes'] ?? 0);
        if ($minutes < 1 || $minutes > 180) return Response::validationError(['minutes' => ['Extend by 1–180 minutes.']]);
        $extended = (new \DateTimeImmutable('now'))->modify("+{$minutes} minutes");
        $this->db->execute('UPDATE meal_sessions SET cutoff_extended_until = ? WHERE id = ?', [$extended->format('Y-m-d H:i:s'), $session['id']]);
        return Response::success(['cutoff_extended_until' => $extended->format(DATE_ATOM)]);
    }

    private function actionCloseEarly(array $session): Response
    {
        // Set cutoff to now (effectively closing ordering)
        $this->db->execute('UPDATE meal_sessions SET cutoff_extended_until = ? WHERE id = ?', [
            (new \DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:s'), $session['id']
        ]);
        return Response::success(['closed' => true]);
    }

    private function actionAdjustCapacity(array $session, array $data): Response
    {
        $capacity = $data['max_orders'] ?? null;
        if ($capacity !== null && ((int) $capacity < 1 || (int) $capacity > 100000)) return Response::validationError(['max_orders' => ['Capacity must be 1–100000 or null for unlimited.']]);
        $this->db->execute('UPDATE meal_sessions SET max_orders = ? WHERE id = ?', [$capacity === null ? null : (int) $capacity, $session['id']]);
        return Response::success(['max_orders' => $capacity === null ? null : (int) $capacity]);
    }

    private function actionMarkSoldOut(array $session, array $data): Response
    {
        $productUuid = $data['product_uuid'] ?? '';
        $soldOut = (bool) ($data['sold_out'] ?? true);
        $product = $this->db->fetchOne('SELECT id FROM products WHERE uuid = ? AND tenant_id = ?', [$productUuid, $session['tenant_id']]);
        if (!$product) return Response::notFound('Product not found.');
        $this->db->execute('UPDATE meal_session_products SET available = ? WHERE meal_session_id = ? AND product_id = ?', [$soldOut ? 0 : 1, $session['id'], $product['id']]);
        return Response::success(['sold_out' => $soldOut]);
    }

    // ── Exception dates (holidays) ─────────────────────────
    public function addException(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $session = $this->db->fetchOne('SELECT id FROM meal_sessions WHERE uuid = ? AND tenant_id = ?', [$params['uuid'], $tenantId]);
        if (!$session) return Response::notFound('Meal session not found.');
        $data = $request->json();
        $date = $data['exception_date'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)) return Response::validationError(['exception_date' => ['Enter a valid date.']]);
        $action = $data['action'] ?? 'closed';
        if (!in_array($action, ['closed', 'extended_cutoff'], true)) return Response::validationError(['action' => ['Choose closed or extended_cutoff.']]);
        $cutoffOverride = ($action === 'extended_cutoff' && !empty($data['cutoff_override'])) ? $data['cutoff_override'] : null;
        $reason = trim((string) ($data['reason'] ?? '')) ?: null;

        $this->db->execute(
            'INSERT INTO meal_session_exceptions (meal_session_id, exception_date, action, cutoff_override, reason) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE action=VALUES(action), cutoff_override=VALUES(cutoff_override), reason=VALUES(reason)',
            [$session['id'], $date, $action, $cutoffOverride, $reason]
        );
        return Response::success(['saved' => true]);
    }

    public function removeException(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $session = $this->db->fetchOne('SELECT id FROM meal_sessions WHERE uuid = ? AND tenant_id = ?', [$params['uuid'], $tenantId]);
        if (!$session) return Response::notFound('Meal session not found.');
        $this->db->execute('DELETE FROM meal_session_exceptions WHERE meal_session_id = ? AND exception_date = ?', [$session['id'], $params['date']]);
        return Response::success(['removed' => true]);
    }

    // ── Kitchen production ─────────────────────────────────
    public function production(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $date = $request->input('date') ?? date('Y-m-d');
        return Response::success($this->service->productionSummary($tenantId, $date));
    }

    public function packingList(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $date = $request->input('date') ?? date('Y-m-d');
        return Response::success($this->service->packingList($tenantId, $params['uuid'], $date));
    }

    // ── Customer favourite sessions ────────────────────────
    public function toggleFavourite(Request $request, array $params): Response
    {
        $tenant = TenantContext::get();
        $customerId = (int) $request->authClaims['id'];
        $session = $this->db->fetchOne('SELECT id FROM meal_sessions WHERE uuid = ? AND tenant_id = ? AND enabled = 1', [$params['uuid'], $tenant->id]);
        if (!$session) return Response::notFound('Meal session not found.');
        $exists = $this->db->fetchOne('SELECT 1 FROM customer_favourite_sessions WHERE customer_id = ? AND meal_session_id = ?', [$customerId, $session['id']]);
        if ($exists) {
            $this->db->execute('DELETE FROM customer_favourite_sessions WHERE customer_id = ? AND meal_session_id = ?', [$customerId, $session['id']]);
            return Response::success(['favourited' => false]);
        }
        $this->db->execute('INSERT IGNORE INTO customer_favourite_sessions (customer_id, meal_session_id) VALUES (?,?)', [$customerId, $session['id']]);
        return Response::success(['favourited' => true]);
    }

    public function myFavourites(Request $request, array $params): Response
    {
        $tenant = TenantContext::get();
        $customerId = (int) $request->authClaims['id'];
        $uuids = array_column($this->db->fetchAll(
            'SELECT ms.uuid FROM customer_favourite_sessions cfs JOIN meal_sessions ms ON ms.id = cfs.meal_session_id WHERE cfs.customer_id = ? AND ms.tenant_id = ? AND ms.enabled = 1',
            [$customerId, $tenant->id]
        ), 'uuid');
        return Response::success($uuids);
    }

    // ── Validation helpers ─────────────────────────────────
    private function validate(array $data): array
    {
        $errors = [];
        if (trim((string) ($data['name'] ?? '')) === '' || mb_strlen((string) $data['name']) > 100) $errors['name'][] = 'Enter a session name up to 100 characters.';
        if (!in_array($data['ordering_mode'] ?? '', ['preorder','instant','both'], true)) $errors['ordering_mode'][] = 'Choose preorder, instant, or both.';
        $days = $data['weekdays'] ?? null;
        if (!is_array($days) || !$days || array_filter($days, fn ($d) => !is_int($d) && !ctype_digit((string) $d)) || array_filter(array_map('intval', $days), fn ($d) => $d < 1 || $d > 7)) $errors['weekdays'][] = 'Choose at least one valid weekday.';
        foreach (['service_start','service_end','opens_at','cutoff_at'] as $field) if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/D', (string) ($data[$field] ?? ''))) $errors[$field][] = 'Enter a valid time.';
        foreach (['opens_day_offset','cutoff_day_offset'] as $field) if (!isset($data[$field]) || (int) $data[$field] < -7 || (int) $data[$field] > 7) $errors[$field][] = 'Day offset must be between -7 and 7.';
        if (isset($data['max_orders']) && $data['max_orders'] !== null && $data['max_orders'] !== '' && ((int) $data['max_orders'] < 1 || (int) $data['max_orders'] > 100000)) $errors['max_orders'][] = 'Capacity must be between 1 and 100000.';
        if (!$errors) {
            $base = new \DateTimeImmutable('2026-01-05 00:00:00');
            $open = $base->modify(((int) $data['opens_day_offset']) . ' days')->setTime(...array_map('intval', explode(':', $data['opens_at'])));
            $cutoff = $base->modify(((int) $data['cutoff_day_offset']) . ' days')->setTime(...array_map('intval', explode(':', $data['cutoff_at'])));
            $serviceStart = $base->setTime(...array_map('intval', explode(':', $data['service_start'])));
            if ($open >= $cutoff) $errors['opens_at'][] = 'Ordering must open before the cutoff.';
            if (($data['ordering_mode'] ?? '') === 'preorder' && $cutoff > $serviceStart) $errors['cutoff_at'][] = 'A preorder-only cutoff cannot be after service starts.';
            if (substr((string) $data['service_start'], 0, 5) === substr((string) $data['service_end'], 0, 5)) $errors['service_end'][] = 'Service start and end cannot be the same.';
        }
        return $errors;
    }

    private function syncProducts(int $sessionId, int $tenantId, array $uuids, array $overrides = []): void
    {
        $this->db->execute('DELETE FROM meal_session_products WHERE meal_session_id = ?', [$sessionId]);
        foreach (array_values(array_unique(array_filter(array_map('strval', $uuids)))) as $uuid) {
            $product = $this->db->fetchOne('SELECT id FROM products WHERE uuid = ? AND tenant_id = ? AND deleted_at IS NULL', [$uuid, $tenantId]);
            if (!$product) throw new \DomainException('One selected product does not belong to this store.');
            $override = $overrides[$uuid] ?? [];
            $this->db->execute(
                'INSERT INTO meal_session_products (meal_session_id, product_id, price_override, quantity_limit, prep_minutes, available) VALUES (?,?,?,?,?,?)',
                [
                    $sessionId, $product['id'],
                    isset($override['price']) && $override['price'] !== null && $override['price'] !== '' ? (int) $override['price'] : null,
                    isset($override['quantity_limit']) && $override['quantity_limit'] !== null && $override['quantity_limit'] !== '' ? (int) $override['quantity_limit'] : null,
                    isset($override['prep_minutes']) && $override['prep_minutes'] !== null && $override['prep_minutes'] !== '' ? (int) $override['prep_minutes'] : null,
                    ($override['available'] ?? true) ? 1 : 0,
                ]
            );
        }
    }

    private function validateProducts(int $tenantId, mixed $uuids): ?string
    {
        if (!is_array($uuids) || $uuids === []) return 'Choose at least one product for this meal session.';
        $unique = array_values(array_unique(array_filter(array_map('strval', $uuids))));
        if ($unique === []) return 'Choose at least one product for this meal session.';
        $marks = implode(',', array_fill(0, count($unique), '?'));
        $row = $this->db->fetchOne("SELECT COUNT(*) AS total FROM products WHERE tenant_id = ? AND uuid IN ({$marks}) AND deleted_at IS NULL", array_merge([$tenantId], $unique));
        return (int) ($row['total'] ?? 0) === count($unique) ? null : 'One or more selected products do not belong to this store.';
    }
}
