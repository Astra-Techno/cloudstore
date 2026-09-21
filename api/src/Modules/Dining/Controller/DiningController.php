<?php
declare(strict_types=1);

namespace App\Modules\Dining\Controller;

use App\Core\Database\Connection;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Modules\Tenant\Repository\CapabilityRepository;
use App\Modules\Auth\Repository\CustomerRepository;
use App\Modules\Catalog\Repository\AddonRepository;
use App\Modules\Catalog\Domain\PricingCalculator;
use App\Modules\Order\Repository\OrderRepository;
use App\Modules\Tenant\Repository\BrandingRepository;
use Ramsey\Uuid\Uuid;

/** QR tokens select a tenant; neither tenant IDs nor prices are trusted from guests. */
final class DiningController
{
    public function __construct(
        private readonly Connection $db,
        private readonly CapabilityRepository $capabilities,
        private readonly CustomerRepository $customers,
        private readonly AddonRepository $addons,
        private readonly OrderRepository $orders,
        private readonly BrandingRepository $branding,
    ) {}

    private function tenant(Request $r): int
    {
        $id = (int) ($r->authClaims['tenant_id'] ?? 0);
        if ($id < 1 || !($this->capabilities->getForTenant($id)['qr_table_ordering'] ?? false)) {
            throw new \DomainException('QR table ordering is not enabled for this store.');
        }
        return $id;
    }

    private function respond(callable $action): Response
    {
        try { return Response::success($action()); }
        catch (\DomainException $e) { return Response::error($e->getMessage(), 'DINING_UNAVAILABLE', 422); }
    }

    public function tables(Request $r, array $p): Response
    {
        return $this->respond(function () use ($r) {
            $tenant = $this->tenant($r);
            $tables = $this->db->fetchAll('SELECT * FROM dining_tables WHERE tenant_id = ? ORDER BY id', [$tenant]);
            foreach ($tables as &$table) {
                $table['session'] = $this->db->fetchOne('SELECT * FROM dining_sessions WHERE table_id = ? AND closed_at IS NULL', [$table['id']]);
                $table['orders'] = $table['session'] ? $this->db->fetchAll('SELECT o.* FROM orders o JOIN dining_orders d ON d.order_id = o.id WHERE d.session_id = ? ORDER BY o.id', [$table['session']['id']]) : [];
                $table['bill_total'] = array_sum(array_map(fn ($o) => in_array($o['status'], ['cancelled', 'rejected', 'refunded']) ? 0 : (int) $o['total'], $table['orders']));
            }
            return $tables;
        });
    }

    public function createTable(Request $r, array $p): Response
    {
        return $this->respond(function () use ($r) {
            $tenant = $this->tenant($r);
            $name = trim((string) ($r->json()['name'] ?? ''));
            if ($name === '' || mb_strlen($name) > 80) throw new \DomainException('Enter a table name up to 80 characters.');
            $this->db->execute('INSERT INTO dining_tables (tenant_id, name, token) VALUES (?, ?, ?)', [$tenant, $name, bin2hex(random_bytes(32))]);
            return ['id' => (int) $this->db->lastInsertId()];
        });
    }

    public function tableAction(Request $r, array $p): Response
    {
        return $this->respond(function () use ($r, $p) {
            $tenant = $this->tenant($r);
            return $this->db->transaction(function () use ($r, $p, $tenant) {
                $table = $this->db->fetchOne('SELECT * FROM dining_tables WHERE id = ? AND tenant_id = ? FOR UPDATE', [(int) $p['id'], $tenant]);
                if (!$table) throw new \DomainException('Table not found.');
                $session = $this->db->fetchOne('SELECT * FROM dining_sessions WHERE table_id = ? AND closed_at IS NULL', [$table['id']]);
                $action = $r->json()['action'] ?? '';
                if ($action === 'open') {
                    if (!$table['enabled']) throw new \DomainException('Enable this table first.');
                    if ($session) return $session;
                    $customer = $this->customers->create(['uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tenant, 'name' => 'Table ' . $table['name']]);
                    $this->db->execute('INSERT INTO dining_sessions (table_id, customer_id, access_code) VALUES (?, ?, ?)', [$table['id'], $customer, (string) random_int(100000, 999999)]);
                } elseif ($action === 'close') {
                    if (!$session) throw new \DomainException('There is no open bill.');
                    $pending = $this->db->fetchOne("SELECT COUNT(*) AS n FROM orders o JOIN dining_orders d ON d.order_id = o.id WHERE d.session_id = ? AND o.status NOT IN ('served','cancelled','rejected','refunded')", [$session['id']]);
                    if ($pending['n'] > 0) throw new \DomainException('Serve or cancel outstanding orders before closing the bill.');
                    if (($r->json()['payment_received'] ?? false) !== true) throw new \DomainException('Confirm payment has been collected.');
                    $this->db->execute("UPDATE orders o JOIN dining_orders d ON d.order_id = o.id SET o.payment_status = 'paid' WHERE d.session_id = ? AND o.status = 'served'", [$session['id']]);
                    $this->db->execute('UPDATE dining_sessions SET closed_at = NOW() WHERE id = ?', [$session['id']]);
                } elseif ($action === 'rename') {
                    $newName = trim((string) ($r->json()['name'] ?? ''));
                    if ($newName === '' || mb_strlen($newName) > 80) throw new \DomainException('Enter a table name up to 80 characters.');
                    $this->db->execute('UPDATE dining_tables SET name = ? WHERE id = ?', [$newName, $table['id']]);
                } elseif ($action === 'delete') {
                    if ($session) throw new \DomainException('Close the current bill before deleting this table.');
                    $hasOrders = $this->db->fetchOne('SELECT 1 FROM dining_orders d JOIN dining_sessions s ON s.id = d.session_id WHERE s.table_id = ? LIMIT 1', [$table['id']]);
                    if ($hasOrders) {
                        $this->db->execute('UPDATE dining_tables SET enabled = 0 WHERE id = ?', [$table['id']]);
                    } else {
                        $this->db->execute('DELETE FROM dining_tables WHERE id = ?', [$table['id']]);
                    }
                } elseif (in_array($action, ['enable', 'disable', 'regenerate'], true)) {
                    if ($session) throw new \DomainException('Close the current bill before changing this table.');
                    if ($action === 'regenerate') $this->db->execute('UPDATE dining_tables SET token = ? WHERE id = ?', [bin2hex(random_bytes(32)), $table['id']]);
                    else $this->db->execute('UPDATE dining_tables SET enabled = ? WHERE id = ?', [$action === 'enable' ? 1 : 0, $table['id']]);
                } else throw new \DomainException('Unknown table action.');
                return ['success' => true];
            });
        });
    }

    private function publicTable(string $token, bool $lock = false): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) throw new \DomainException('This table link is invalid.');
        $table = $this->db->fetchOne('SELECT t.*, s.name AS store_name, s.configuration, s.timezone, s.status AS store_status FROM dining_tables t JOIN tenants s ON s.id = t.tenant_id WHERE t.token = ?' . ($lock ? ' FOR UPDATE' : ''), [$token]);
        if (!$table || !$table['enabled'] || $table['store_status'] !== 'active' || !($this->capabilities->getForTenant((int) $table['tenant_id'])['qr_table_ordering'] ?? false)) {
            throw new \DomainException('Table ordering is unavailable. Please speak to the staff.');
        }
        return $table;
    }

    public function menu(Request $r, array $p): Response
    {
        return $this->respond(function () use ($p) {
            $table = $this->publicTable($p['token']);
            $products = $this->db->fetchAll("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.tenant_id = ? AND p.status = 'active' AND p.deleted_at IS NULL ORDER BY p.sort_order, p.name", [$table['tenant_id']]);
            $menu = [];
            foreach ($products as $product) {
                $variants = $this->db->fetchAll("SELECT uuid, name, price, stock_mode, stock_quantity FROM product_variants WHERE product_id = ? AND status = 'active' ORDER BY sort_order", [$product['id']]);
                $menu[] = [
                    'uuid' => $product['uuid'], 'name' => $product['name'], 'description' => $product['short_description'],
                    'category' => $product['category_name'] ?? 'Menu', 'price' => PricingCalculator::getEffectivePrice($product),
                    'available' => $product['stock_mode'] !== 'limited_stock' || (int) $product['stock_quantity'] > 0,
                    'variants' => $variants, 'addons' => $this->addons->getGroupsWithItemsForProduct((int) $product['id']),
                ];
            }
            $config = json_decode($table['configuration'] ?? '{}', true) ?: [];
            $branding = $this->branding->findByTenant((int) $table['tenant_id']);
            $brandData = ['primary_color' => $branding['primary_color'] ?? '#E23744', 'logo_url' => $branding['logo_url'] ?? null, 'tagline' => $config['branding_tagline'] ?? null];
            return ['store' => $table['store_name'], 'table' => $table['name'], 'branding' => $brandData, 'products' => $menu,
                'session_open' => (bool) $this->db->fetchOne('SELECT id FROM dining_sessions WHERE table_id = ? AND closed_at IS NULL', [$table['id']])];
        });
    }

    public function identify(Request $r, array $p): Response
    {
        return $this->respond(function () use ($r, $p) {
            $table = $this->publicTable($p['token']);
            $tenant = (int) $table['tenant_id'];
            $phone = preg_replace('/\D/', '', trim((string) ($r->json()['phone'] ?? '')));
            if (strlen($phone) < 10) throw new \DomainException('Enter a valid mobile number.');
            $phone = substr($phone, -10);
            $customer = $this->db->fetchOne('SELECT id, name, phone FROM customers WHERE phone = ? AND tenant_id = ? AND deleted_at IS NULL', [$phone, $tenant]);
            $suggestions = [];
            if ($customer) {
                $recent = $this->db->fetchAll(
                    "SELECT oi.product_snapshot, oi.variant_snapshot, oi.quantity, oi.unit_price
                     FROM order_items oi JOIN orders o ON o.id = oi.order_id
                     WHERE o.customer_id = ? AND o.tenant_id = ? AND o.status NOT IN ('cancelled','rejected','refunded')
                     ORDER BY o.id DESC LIMIT 30",
                    [$customer['id'], $tenant]
                );
                $seen = [];
                foreach ($recent as $item) {
                    $snap = json_decode($item['product_snapshot'], true);
                    $name = $snap['name'] ?? '';
                    if (!$name || isset($seen[$name])) continue;
                    $seen[$name] = true;
                    $suggestions[] = ['name' => $name, 'price' => (int) $item['unit_price']];
                    if (count($suggestions) >= 8) break;
                }
            }
            // Link phone to the dining session's customer record
            $session = $this->db->fetchOne('SELECT * FROM dining_sessions WHERE table_id = ? AND closed_at IS NULL', [$table['id']]);
            if ($session) {
                if ($customer) {
                    // Update session to use the real customer
                    $this->db->execute('UPDATE dining_sessions SET customer_id = ? WHERE id = ?', [$customer['id'], $session['id']]);
                    // Update existing orders in this session too
                    $this->db->execute('UPDATE orders o JOIN dining_orders d ON d.order_id = o.id SET o.customer_id = ? WHERE d.session_id = ?', [$customer['id'], $session['id']]);
                } else {
                    // Create a real customer with this phone
                    $newId = $this->customers->create(['uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tenant, 'phone' => $phone]);
                    $this->db->execute('UPDATE dining_sessions SET customer_id = ? WHERE id = ?', [$newId, $session['id']]);
                }
            }
            return ['returning' => $customer !== null, 'name' => $customer['name'] ?? null, 'suggestions' => $suggestions];
        });
    }

    public function placeOrder(Request $r, array $p): Response
    {
        return $this->respond(function () use ($r, $p) {
            $data = $r->json();
            if (!Uuid::isValid((string) ($data['request_key'] ?? ''))) throw new \DomainException('Refresh the menu and try again.');
            if (!is_array($data['items'] ?? null) || count($data['items']) < 1 || count($data['items']) > 50) throw new \DomainException('Select between 1 and 50 items.');
            return $this->db->transaction(function () use ($data, $p) {
                $table = $this->publicTable($p['token'], true);
                $tenant = (int) $table['tenant_id'];
                $session = $this->db->fetchOne('SELECT * FROM dining_sessions WHERE table_id = ? AND closed_at IS NULL', [$table['id']]);
                if (!$session || !hash_equals($session['access_code'], (string) ($data['access_code'] ?? ''))) throw new \DomainException('Ask the staff for the current six-digit table code.');
                $previous = $this->db->fetchOne('SELECT receipt_token FROM dining_orders WHERE session_id = ? AND request_key = ?', [$session['id'], $data['request_key']]);
                if ($previous) return $previous;
                $subtotal = 0;
                $lines = [];
                foreach ($data['items'] as $line) {
                    if (!is_array($line)) throw new \DomainException('Invalid item.');
                    $qty = filter_var($line['quantity'] ?? null, FILTER_VALIDATE_INT);
                    if ($qty === false || $qty < 1 || $qty > 99) throw new \DomainException('Quantity must be between 1 and 99.');
                    $product = $this->db->fetchOne("SELECT * FROM products WHERE uuid = ? AND tenant_id = ? AND status = 'active' AND deleted_at IS NULL FOR UPDATE", [(string) ($line['product_uuid'] ?? ''), $tenant]);
                    if (!$product) throw new \DomainException('An item is no longer available. Refresh the menu.');
                    $variant = null;
                    $variants = $this->db->fetchAll("SELECT * FROM product_variants WHERE product_id = ? AND status = 'active' FOR UPDATE", [$product['id']]);
                    foreach ($variants as $v) if ($v['uuid'] === ($line['variant_uuid'] ?? '')) $variant = $v;
                    if (($variants || !empty($line['variant_uuid'])) && !$variant) throw new \DomainException('Choose an available option for ' . $product['name']);
                    if ($product['pricing_mode'] !== 'fixed' && !$variant) throw new \DomainException('This item needs a priced option. Please ask staff.');
                    foreach ([['products', $product], ['product_variants', $variant]] as [$stockTable, $stock]) {
                        if ($stock && $stock['stock_mode'] === 'limited_stock') {
                            $changed = $this->db->execute("UPDATE {$stockTable} SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?", [$qty, $stock['id'], $qty]);
                            if (!$changed) throw new \DomainException('Insufficient stock for ' . $product['name']);
                        }
                    }
                    $selected = $line['addon_ids'] ?? [];
                    if (!is_array($selected) || count($selected) > 50) throw new \DomainException('Invalid extras.');
                    $selected = array_unique(array_map('intval', $selected));
                    $extras = []; $extraPrice = 0; $validIds = [];
                    foreach ($this->addons->getGroupsWithItemsForProduct((int) $product['id']) as $group) {
                        $count = 0;
                        foreach ($group['items'] as $item) {
                            if ($item['status'] !== 'active') continue;
                            $validIds[] = (int) $item['id'];
                            if (in_array((int) $item['id'], $selected, true)) {
                                $count++; $extraPrice += (int) $item['price'];
                                $extras[] = ['name' => $item['name'], 'price' => (int) $item['price']];
                            }
                        }
                        if ($count < max((int) $group['min_selections'], $group['is_required'] ? 1 : 0) || $count > (int) $group['max_selections']) throw new \DomainException('Check selections for ' . $group['name']);
                    }
                    if (array_diff($selected, $validIds)) throw new \DomainException('An extra is no longer available.');
                    $price = PricingCalculator::getEffectivePrice($product, $variant);
                    $total = ($price + $extraPrice) * $qty; $subtotal += $total;
                    $lines[] = ['product_id' => $product['id'], 'variant_id' => $variant['id'] ?? null, 'product_snapshot' => json_encode(['name' => $product['name']]), 'variant_snapshot' => $variant ? json_encode(['name' => $variant['name']]) : null,
                        'addons_snapshot' => json_encode($extras), 'quantity' => $qty, 'unit_price' => $price, 'addons_price' => $extraPrice, 'line_total' => $total];
                }
                $config = json_decode($table['configuration'] ?? '{}', true) ?: [];
                $tax = (int) round($subtotal * max(0, min(100, (float) ($config['tax_rate'] ?? 0))) / 100);
                $orderId = $this->orders->create(['uuid' => Uuid::uuid4()->toString(), 'order_number' => 'DIN-' . strtoupper(bin2hex(random_bytes(8))),
                    'tenant_id' => $tenant, 'customer_id' => $session['customer_id'],
                    'status' => 'confirmed', 'order_type' => 'dine_in',
                    'subtotal' => $subtotal, 'tax_amount' => $tax, 'total' => $subtotal + $tax, 'payment_method' => 'pay_at_counter', 'payment_status' => 'pending',
                    'notes' => mb_substr(trim((string) ($data['notes'] ?? '')), 0, 500), 'address_snapshot' => json_encode(['table_name' => $table['name']])]);
                foreach ($lines as $line) $this->orders->addItem(['order_id' => $orderId] + $line);
                $this->orders->addStatusHistory($orderId, null, 'confirmed', 'customer', (int) $session['customer_id'], 'QR table order');
                $receipt = bin2hex(random_bytes(32));
                $this->db->execute('INSERT INTO dining_orders (order_id, session_id, request_key, receipt_token) VALUES (?, ?, ?, ?)', [$orderId, $session['id'], $data['request_key'], $receipt]);
                return ['receipt_token' => $receipt];
            });
        });
    }

    public function receipt(Request $r, array $p): Response
    {
        return $this->respond(function () use ($p) {
            $row = $this->db->fetchOne('SELECT o.id, o.order_number, o.status, o.subtotal, o.tax_amount, o.total, o.payment_status FROM orders o JOIN dining_orders d ON d.order_id = o.id WHERE d.receipt_token = ?', [$p['token']]);
            if (!$row) throw new \DomainException('Order not found.');
            $row['items'] = $this->orders->getItems((int) $row['id']); unset($row['id']);
            return $row;
        });
    }
}
