<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database\Connection;
use App\Modules\Catalog\Repository\CategoryRepository;
use App\Modules\Catalog\Repository\ProductRepository;
use App\Modules\Catalog\Repository\VariantRepository;
use App\Modules\Catalog\Repository\AddonRepository;
use App\Modules\Delivery\Repository\DeliveryZoneRepository;
use App\Modules\Tenant\Repository\TenantRepository;
use App\Modules\Tenant\Repository\BrandingRepository;
use App\Modules\Tenant\Repository\CapabilityRepository;
use App\Modules\Tenant\Service\TenantService;
use App\Modules\Tenant\Service\AppTokenService;
use App\Modules\Tenant\Repository\AppTokenRepository;
use App\Modules\Auth\Repository\AdminRepository;
use App\Modules\Auth\Service\PasswordService;
use App\Modules\Auth\Domain\Role;
use App\Modules\Order\Repository\OrderRepository;
use Ramsey\Uuid\Uuid;

/**
 * Seeds "Annapurna Veg Kitchen" — a vegetarian cloud kitchen tenant
 * that showcases meal sessions, preorder windows, and session-based pricing.
 */
final class AnnapurnaSeeder
{
    private int $tid;
    private Connection $db;
    private string $timezone = 'Asia/Kolkata';

    /** product_id => slug (for meal session assignments) */
    private array $products = [];

    public function run(Connection $db): array
    {
        $this->db = $db;

        // ── 1. Tenant ──
        $tenantRepo = new TenantRepository($db);
        $brandingRepo = new BrandingRepository($db);
        $capabilityRepo = new CapabilityRepository($db);
        $tenantService = new TenantService($tenantRepo, $brandingRepo, $capabilityRepo);
        $tokenService = new AppTokenService(new AppTokenRepository($db), new \App\Core\Config\Config());

        $tenant = $tenantService->create([
            'name' => 'Annapurna Veg Kitchen',
            'slug' => 'annapurna-veg',
            'business_type' => 'cloud_kitchen',
            'status' => 'active',
            'contact_phone' => '+919876543250',
            'contact_email' => 'annapurna@example.com',
            'address' => '42 Gandhi Nagar, Coimbatore',
            'configuration' => [
                'delivery' => ['latitude' => 11.0168, 'longitude' => 76.9558],
                'delivery_enabled' => true,
                'pickup_enabled' => true,
                'payment_methods' => ['cod', 'razorpay'],
                'min_order_amount' => 10000, // ₹100
                'tax_rate' => 5, // 5% GST
                'service_charge_percent' => 0,
                'delivery_charge_fixed' => 3000, // ₹30 fallback
                'business_hours' => [
                    'monday'    => ['open_time' => '06:00', 'close_time' => '22:00'],
                    'tuesday'   => ['open_time' => '06:00', 'close_time' => '22:00'],
                    'wednesday' => ['open_time' => '06:00', 'close_time' => '22:00'],
                    'thursday'  => ['open_time' => '06:00', 'close_time' => '22:00'],
                    'friday'    => ['open_time' => '06:00', 'close_time' => '22:00'],
                    'saturday'  => ['open_time' => '06:00', 'close_time' => '22:00'],
                    'sunday'    => ['open_time' => '08:00', 'close_time' => '14:00'],
                ],
            ],
        ]);

        $this->tid = $tenant->id;

        $brandingRepo->upsert($this->tid, [
            'primary_color' => '#059669',
            'secondary_color' => '#065F46',
            'font' => 'Nunito',
        ]);

        $token = $tokenService->generate($this->tid);

        // ── 2. Admin ──
        $adminRepo = new AdminRepository($db);
        $passwordService = new PasswordService();
        $adminId = $adminRepo->create([
            'uuid' => Uuid::uuid4()->toString(),
            'tenant_id' => $this->tid,
            'name' => 'Annapurna Admin',
            'email' => 'annapurna-veg@cloudstore.com',
            'password_hash' => $passwordService->hash('Admin@123'),
            'role' => Role::TENANT_OWNER,
            'status' => 'active',
        ]);
        $adminRepo->setPermissions($adminId, Role::getDefaultPermissions(Role::TENANT_OWNER));

        echo "  Created tenant: Annapurna Veg Kitchen\n";

        // ── 3. Catalog ──
        $this->seedCatalog();

        // ── 4. Delivery Zones ──
        $this->seedDeliveryZones();

        // ── 5. Meal Sessions ──
        $sessionIds = $this->seedMealSessions();

        // ── 6. Customer + Address ──
        $customerId = $this->seedCustomer();

        // ── 7. Sample Orders ──
        $this->seedOrders($customerId, $sessionIds);

        // ── 8. Meal Session Exceptions ──
        $this->seedExceptions($sessionIds);

        echo "  Seeded complete Annapurna Veg Kitchen dataset\n";

        return [
            'tenant' => 'Annapurna Veg Kitchen',
            'slug' => 'annapurna-veg',
            'app_token' => $token['token'],
            'admin_email' => 'annapurna-veg@cloudstore.com',
        ];
    }

    // ═══════════════════════════════════════════
    //  CATALOG — 6 categories, ~28 products
    // ═══════════════════════════════════════════

    private function seedCatalog(): void
    {
        $catRepo = new CategoryRepository($this->db);
        $prodRepo = new ProductRepository($this->db);
        $varRepo = new VariantRepository($this->db);
        $addonRepo = new AddonRepository($this->db);

        // ── Addon Groups (shared) ──
        $spiceGroup = $addonRepo->createGroup([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $this->tid,
            'name' => 'Spice Level', 'is_required' => 1, 'min_selections' => 1, 'max_selections' => 1,
        ]);
        foreach (['Mild' => 0, 'Medium' => 0, 'Spicy' => 0] as $name => $price) {
            $addonRepo->createItem(['uuid' => Uuid::uuid4()->toString(), 'group_id' => $spiceGroup, 'name' => $name, 'price' => $price, 'sort_order' => 0]);
        }

        $sidesGroup = $addonRepo->createGroup([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $this->tid,
            'name' => 'Extra Sides', 'is_required' => 0, 'min_selections' => 0, 'max_selections' => 3,
        ]);
        foreach (['Raita' => 2000, 'Papad (2 pcs)' => 1500, 'Pickle' => 1000, 'Extra Sambar' => 2500] as $name => $price) {
            $addonRepo->createItem(['uuid' => Uuid::uuid4()->toString(), 'group_id' => $sidesGroup, 'name' => $name, 'price' => $price, 'sort_order' => 0]);
        }

        $beverageAddon = $addonRepo->createGroup([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $this->tid,
            'name' => 'Add a Drink', 'is_required' => 0, 'min_selections' => 0, 'max_selections' => 1,
        ]);
        foreach (['Buttermilk' => 2000, 'Filter Coffee' => 3000, 'Fresh Lime Soda' => 3500] as $name => $price) {
            $addonRepo->createItem(['uuid' => Uuid::uuid4()->toString(), 'group_id' => $beverageAddon, 'name' => $name, 'price' => $price, 'sort_order' => 0]);
        }

        // ── Category 1: Tiffin Boxes ──
        $cat1 = $catRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $this->tid,
            'name' => 'Tiffin Boxes', 'slug' => 'tiffin-boxes', 'sort_order' => 1,
            'description' => 'Complete meal boxes packed fresh for every session',
            'image_url' => 'https://placehold.co/400x300/059669/white?text=Tiffin+Boxes',
        ]);

        $this->product($prodRepo, $cat1, 'Mini Tiffin', 'mini-tiffin', 7500,
            'Sambar rice, poriyal, rasam, curd — a light wholesome meal', 'plate', 'simple', 15, true);
        $addonRepo->attachGroupToProduct($this->lastPid(), $sidesGroup);

        $this->product($prodRepo, $cat1, 'Standard Tiffin', 'standard-tiffin', 12000,
            'Rice, sambar, rasam, kootu, poriyal, curd, pickle, papad', 'plate', 'simple', 20, true);
        $addonRepo->attachGroupToProduct($this->lastPid(), $sidesGroup);
        $addonRepo->attachGroupToProduct($this->lastPid(), $beverageAddon);

        $this->product($prodRepo, $cat1, 'Premium Thali', 'premium-thali', 18000,
            'Full thali with 2 curries, dal, rasam, rice, puri, sweet, papad, pickle', 'plate', 'simple', 25, true);
        $addonRepo->attachGroupToProduct($this->lastPid(), $sidesGroup);
        $addonRepo->attachGroupToProduct($this->lastPid(), $beverageAddon);

        $this->product($prodRepo, $cat1, 'Diet Tiffin', 'diet-tiffin', 9500,
            'Brown rice, steamed veggies, dal, salad — low oil, no ghee', 'plate', 'simple', 15, false);

        // ── Category 2: Rice & Curry ──
        $cat2 = $catRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $this->tid,
            'name' => 'Rice & Curry', 'slug' => 'rice-curry', 'sort_order' => 2,
            'description' => 'Hearty rice dishes and traditional curries',
            'image_url' => 'https://placehold.co/400x300/059669/white?text=Rice+Curry',
        ]);

        $p = $this->product($prodRepo, $cat2, 'Veg Biryani', 'veg-biryani', 14000,
            'Fragrant basmati rice with mixed vegetables, saffron, and dum-style cooking', 'plate', 'variable', 25, true);
        $varRepo->create(['uuid' => Uuid::uuid4()->toString(), 'product_id' => $p, 'name' => 'Regular', 'price' => 14000, 'sort_order' => 0]);
        $varRepo->create(['uuid' => Uuid::uuid4()->toString(), 'product_id' => $p, 'name' => 'Family Pack', 'price' => 35000, 'sort_order' => 1]);
        $addonRepo->attachGroupToProduct($p, $spiceGroup);
        $addonRepo->attachGroupToProduct($p, $sidesGroup);

        $p = $this->product($prodRepo, $cat2, 'Curd Rice', 'curd-rice', 6000,
            'Cooling tempered curd rice with pomegranate and grapes', 'plate', 'variable', 10, false);
        $varRepo->create(['uuid' => Uuid::uuid4()->toString(), 'product_id' => $p, 'name' => '250g', 'price' => 6000, 'weight_grams' => 250, 'sort_order' => 0]);
        $varRepo->create(['uuid' => Uuid::uuid4()->toString(), 'product_id' => $p, 'name' => '500g', 'price' => 11000, 'weight_grams' => 500, 'sort_order' => 1]);

        $this->product($prodRepo, $cat2, 'Sambar Rice', 'sambar-rice', 8000,
            'Well-mixed sambar rice with ghee and papad', 'plate', 'simple', 12, false);
        $addonRepo->attachGroupToProduct($this->lastPid(), $spiceGroup);

        $this->product($prodRepo, $cat2, 'Lemon Rice', 'lemon-rice', 7000,
            'Tangy lemon rice tempered with mustard, curry leaves, and peanuts', 'plate', 'simple', 10, false);

        $this->product($prodRepo, $cat2, 'Tamarind Rice', 'tamarind-rice', 7000,
            'Puliyodarai — temple-style tamarind rice', 'plate', 'simple', 10, false);

        $this->product($prodRepo, $cat2, 'Paneer Butter Masala', 'paneer-butter-masala', 16000,
            'Creamy tomato gravy with soft paneer cubes', 'plate', 'simple', 20, true);
        $addonRepo->attachGroupToProduct($this->lastPid(), $spiceGroup);

        // ── Category 3: Breakfast Items ──
        $cat3 = $catRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $this->tid,
            'name' => 'Breakfast', 'slug' => 'breakfast', 'sort_order' => 3,
            'description' => 'Traditional South Indian breakfast favourites',
            'image_url' => 'https://placehold.co/400x300/059669/white?text=Breakfast',
        ]);

        $p = $this->product($prodRepo, $cat3, 'Idli', 'idli', 4000,
            'Soft steamed rice cakes served with sambar and chutneys', 'plate', 'variable', 10, true);
        $varRepo->create(['uuid' => Uuid::uuid4()->toString(), 'product_id' => $p, 'name' => '3 pcs', 'price' => 4000, 'sort_order' => 0]);
        $varRepo->create(['uuid' => Uuid::uuid4()->toString(), 'product_id' => $p, 'name' => '5 pcs', 'price' => 6000, 'sort_order' => 1]);

        $p = $this->product($prodRepo, $cat3, 'Masala Dosa', 'masala-dosa', 7000,
            'Crispy crepe filled with spiced potato masala', 'plate', 'simple', 15, true);
        $addonRepo->attachGroupToProduct($p, $spiceGroup);

        $this->product($prodRepo, $cat3, 'Pongal', 'pongal', 5500,
            'Pepper-cumin tempered rice-lentil porridge with ghee', 'plate', 'simple', 12, false);

        $this->product($prodRepo, $cat3, 'Upma', 'upma', 4500,
            'Semolina upma with vegetables and cashews', 'plate', 'simple', 10, false);

        $this->product($prodRepo, $cat3, 'Poori Masala', 'poori-masala', 6000,
            'Deep-fried puffed bread with potato curry (4 pooris)', 'plate', 'simple', 15, false);

        $p = $this->product($prodRepo, $cat3, 'Mini Idli Sambar', 'mini-idli-sambar', 5000,
            'Bite-sized idlis dunked in aromatic sambar', 'plate', 'variable', 10, false);
        $varRepo->create(['uuid' => Uuid::uuid4()->toString(), 'product_id' => $p, 'name' => '12 pcs', 'price' => 5000, 'sort_order' => 0]);
        $varRepo->create(['uuid' => Uuid::uuid4()->toString(), 'product_id' => $p, 'name' => '20 pcs', 'price' => 8000, 'sort_order' => 1]);

        // ── Category 4: Snacks ──
        $cat4 = $catRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $this->tid,
            'name' => 'Snacks', 'slug' => 'snacks', 'sort_order' => 4,
            'description' => 'Hot evening snacks and teatime bites',
            'image_url' => 'https://placehold.co/400x300/059669/white?text=Snacks',
        ]);

        $this->product($prodRepo, $cat4, 'Medu Vada', 'medu-vada', 5000,
            'Crispy urad dal doughnuts with sambar and chutney (3 pcs)', 'plate', 'simple', 12, true);

        $this->product($prodRepo, $cat4, 'Onion Bajji', 'onion-bajji', 4500,
            'Sliced onion rings in spiced gram flour batter (8 pcs)', 'plate', 'simple', 10, false);
        $addonRepo->attachGroupToProduct($this->lastPid(), $spiceGroup);

        $this->product($prodRepo, $cat4, 'Samosa', 'samosa', 3000,
            'Crispy pastry with spiced potato-peas filling (2 pcs)', 'piece', 'simple', 10, true);

        $this->product($prodRepo, $cat4, 'Vegetable Cutlet', 'veg-cutlet', 5000,
            'Pan-fried mixed vegetable patties (3 pcs)', 'plate', 'simple', 15, false);

        $this->product($prodRepo, $cat4, 'Bread Pakora', 'bread-pakora', 4000,
            'Stuffed bread fritters with mint chutney (2 pcs)', 'plate', 'simple', 10, false);

        // ── Category 5: Sweets & Desserts ──
        $cat5 = $catRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $this->tid,
            'name' => 'Sweets & Desserts', 'slug' => 'sweets-desserts', 'sort_order' => 5,
            'description' => 'Traditional Indian sweets made fresh daily',
            'image_url' => 'https://placehold.co/400x300/059669/white?text=Sweets',
        ]);

        $this->product($prodRepo, $cat5, 'Kesari', 'kesari', 5000,
            'Saffron semolina halwa with cashews and ghee', 'plate', 'simple', 10, true,
            'limited_stock', 30);

        $this->product($prodRepo, $cat5, 'Payasam', 'payasam', 6000,
            'Creamy vermicelli kheer with cardamom and dry fruits', 'glass', 'simple', 15, false,
            'limited_stock', 25);

        $this->product($prodRepo, $cat5, 'Gulab Jamun', 'gulab-jamun', 4000,
            'Soft milk dumplings in rose-cardamom syrup (3 pcs)', 'plate', 'simple', 8, false);

        // ── Category 6: Beverages ──
        $cat6 = $catRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $this->tid,
            'name' => 'Beverages', 'slug' => 'beverages', 'sort_order' => 6,
            'description' => 'Refreshing drinks and traditional brews',
            'image_url' => 'https://placehold.co/400x300/059669/white?text=Beverages',
        ]);

        $this->product($prodRepo, $cat6, 'Filter Coffee', 'filter-coffee', 3000,
            'Traditional South Indian filter coffee with fresh milk', 'glass', 'simple', 5, true);

        $this->product($prodRepo, $cat6, 'Buttermilk', 'buttermilk', 2000,
            'Spiced churned buttermilk with curry leaves and ginger', 'glass', 'simple', 3, false);

        $this->product($prodRepo, $cat6, 'Fresh Lime Soda', 'fresh-lime-soda', 3500,
            'Sweet or salted lime soda with mint', 'glass', 'simple', 5, false);

        $this->product($prodRepo, $cat6, 'Mango Lassi', 'mango-lassi', 5000,
            'Thick mango yoghurt smoothie (seasonal)', 'glass', 'simple', 5, false,
            'limited_stock', 20);

        echo "    Catalog: 6 categories, " . count($this->products) . " products\n";
    }

    private int $_lastPid = 0;

    private function product(
        ProductRepository $prodRepo, int $catId, string $name, string $slug,
        int $basePrice, string $desc, string $unit, string $type,
        int $prepTime, bool $featured, string $stockMode = 'unlimited', ?int $stockQty = null
    ): int {
        $id = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $this->tid,
            'category_id' => $catId, 'name' => $name, 'slug' => $slug,
            'description' => $desc, 'base_price' => $basePrice,
            'pricing_mode' => 'fixed', 'unit' => $unit, 'product_type' => $type,
            'preparation_time_minutes' => $prepTime, 'is_featured' => $featured ? 1 : 0,
            'stock_mode' => $stockMode, 'stock_quantity' => $stockQty,
        ]);
        $this->addImage($id, $slug);
        $this->products[$slug] = $id;
        $this->_lastPid = $id;
        return $id;
    }

    private function lastPid(): int { return $this->_lastPid; }

    private function addImage(int $productId, string $slug): void
    {
        $label = urlencode(str_replace('-', ' ', $slug));
        $url = "https://placehold.co/400x300/059669/white?text={$label}";
        $this->db->execute(
            "INSERT INTO product_images (product_id, url, alt_text, sort_order, is_primary) VALUES (?, ?, ?, 0, 1)",
            [$productId, $url, $slug]
        );
    }

    // ═══════════════════════════════════════════
    //  DELIVERY ZONES
    // ═══════════════════════════════════════════

    private function seedDeliveryZones(): void
    {
        $zoneRepo = new DeliveryZoneRepository($this->db);
        $zones = [
            ['name' => 'Nearby (0-3 km)',    'min' => 0, 'max' => 3,  'fee' => 2000, 'free_above' => 30000],
            ['name' => 'Mid (3-6 km)',        'min' => 3, 'max' => 6,  'fee' => 4000, 'free_above' => 50000],
            ['name' => 'Extended (6-10 km)',  'min' => 6, 'max' => 10, 'fee' => 6000, 'free_above' => null],
        ];
        foreach ($zones as $i => $z) {
            $zoneRepo->create([
                'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $this->tid,
                'name' => $z['name'], 'min_distance_km' => $z['min'], 'max_distance_km' => $z['max'],
                'fee' => $z['fee'], 'min_order_free_delivery' => $z['free_above'], 'sort_order' => $i,
            ]);
        }
        echo "    Delivery zones: " . count($zones) . "\n";
    }

    // ═══════════════════════════════════════════
    //  MEAL SESSIONS — 5 sessions
    // ═══════════════════════════════════════════

    private function seedMealSessions(): array
    {
        $allWeekdays = '["monday","tuesday","wednesday","thursday","friday","saturday","sunday"]';
        $weekdaysOnly = '["monday","tuesday","wednesday","thursday","friday","saturday"]';
        $sundayOnly = '["sunday"]';

        $sessions = [
            [
                'name' => 'Breakfast Tiffin',
                'ordering_mode' => 'preorder',
                'weekdays' => $weekdaysOnly,
                'service_start' => '07:30:00', 'service_end' => '09:30:00',
                'opens_day_offset' => -1, 'opens_at' => '20:00:00',
                'cutoff_day_offset' => 0, 'cutoff_at' => '06:30:00',
                'max_orders' => 50, 'delivery_fee_override' => 2000, // ₹20
                'min_order_amount' => null, 'sort_order' => 1,
            ],
            [
                'name' => 'Lunch Thali',
                'ordering_mode' => 'both',
                'weekdays' => $allWeekdays,
                'service_start' => '12:00:00', 'service_end' => '14:00:00',
                'opens_day_offset' => -1, 'opens_at' => '20:00:00',
                'cutoff_day_offset' => 0, 'cutoff_at' => '10:00:00',
                'max_orders' => 80, 'delivery_fee_override' => null,
                'min_order_amount' => 15000, // ₹150 minimum
                'sort_order' => 2,
            ],
            [
                'name' => 'Evening Snacks',
                'ordering_mode' => 'instant',
                'weekdays' => $allWeekdays,
                'service_start' => '16:00:00', 'service_end' => '18:00:00',
                'opens_day_offset' => 0, 'opens_at' => '10:00:00',
                'cutoff_day_offset' => 0, 'cutoff_at' => '15:00:00',
                'max_orders' => 40, 'delivery_fee_override' => null,
                'min_order_amount' => null, 'sort_order' => 3,
            ],
            [
                'name' => 'Dinner Tiffin',
                'ordering_mode' => 'preorder',
                'weekdays' => $weekdaysOnly,
                'service_start' => '19:30:00', 'service_end' => '21:30:00',
                'opens_day_offset' => 0, 'opens_at' => '10:00:00',
                'cutoff_day_offset' => 0, 'cutoff_at' => '18:30:00',
                'max_orders' => 60, 'delivery_fee_override' => null,
                'min_order_amount' => 12000, // ₹120 minimum
                'sort_order' => 4,
            ],
            [
                'name' => 'Sunday Special Brunch',
                'ordering_mode' => 'preorder',
                'weekdays' => $sundayOnly,
                'service_start' => '10:00:00', 'service_end' => '13:00:00',
                'opens_day_offset' => -2, 'opens_at' => '18:00:00', // Opens Friday 6 PM
                'cutoff_day_offset' => -1, 'cutoff_at' => '20:00:00', // Cutoff Saturday 8 PM
                'max_orders' => 30, 'delivery_fee_override' => 0, // Free delivery!
                'min_order_amount' => 20000, // ₹200 minimum
                'sort_order' => 5,
            ],
        ];

        $sessionIds = [];

        foreach ($sessions as $s) {
            $uuid = Uuid::uuid4()->toString();
            $this->db->execute(
                "INSERT INTO meal_sessions (uuid, tenant_id, name, ordering_mode, weekdays, service_start, service_end,
                 opens_day_offset, opens_at, cutoff_day_offset, cutoff_at, max_orders, delivery_fee_override,
                 min_order_amount, enabled, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)",
                [
                    $uuid, $this->tid, $s['name'], $s['ordering_mode'], $s['weekdays'],
                    $s['service_start'], $s['service_end'],
                    $s['opens_day_offset'], $s['opens_at'],
                    $s['cutoff_day_offset'], $s['cutoff_at'],
                    $s['max_orders'], $s['delivery_fee_override'], $s['min_order_amount'],
                    $s['sort_order'],
                ]
            );
            $sessionId = (int) $this->db->lastInsertId();
            $sessionIds[$s['name']] = ['id' => $sessionId, 'uuid' => $uuid];
        }

        // ── Product assignments per session with overrides ──
        $this->assignSessionProducts($sessionIds);

        echo "    Meal sessions: " . count($sessionIds) . "\n";
        return $sessionIds;
    }

    private function assignSessionProducts(array $sessionIds): void
    {
        // Breakfast Tiffin — breakfast items, some tiffin boxes, beverages
        $breakfast = $sessionIds['Breakfast Tiffin']['id'];
        $this->sessionProduct($breakfast, 'idli', null, null, 10, true);
        $this->sessionProduct($breakfast, 'masala-dosa', null, null, 10, true);
        $this->sessionProduct($breakfast, 'pongal', null, null, 8, true);
        $this->sessionProduct($breakfast, 'upma', null, null, 8, true);
        $this->sessionProduct($breakfast, 'poori-masala', null, null, 8, true);
        $this->sessionProduct($breakfast, 'mini-idli-sambar', null, null, 8, true);
        $this->sessionProduct($breakfast, 'medu-vada', null, null, 10, true);
        $this->sessionProduct($breakfast, 'filter-coffee', null, null, null, true);
        $this->sessionProduct($breakfast, 'buttermilk', null, null, null, true);
        $this->sessionProduct($breakfast, 'kesari', 4000, 20, 5, true); // Cheaper in morning

        // Lunch Thali — tiffin boxes, rice dishes, curries, sweets, beverages
        $lunch = $sessionIds['Lunch Thali']['id'];
        $this->sessionProduct($lunch, 'mini-tiffin', null, null, null, true);
        $this->sessionProduct($lunch, 'standard-tiffin', null, null, null, true);
        $this->sessionProduct($lunch, 'premium-thali', null, null, null, true);
        $this->sessionProduct($lunch, 'diet-tiffin', null, null, null, true);
        $this->sessionProduct($lunch, 'veg-biryani', null, null, null, true);
        $this->sessionProduct($lunch, 'curd-rice', null, null, null, true);
        $this->sessionProduct($lunch, 'sambar-rice', null, null, null, true);
        $this->sessionProduct($lunch, 'lemon-rice', null, null, null, true);
        $this->sessionProduct($lunch, 'tamarind-rice', null, null, null, true);
        $this->sessionProduct($lunch, 'paneer-butter-masala', null, null, null, true);
        $this->sessionProduct($lunch, 'payasam', null, null, null, true);
        $this->sessionProduct($lunch, 'gulab-jamun', null, null, null, true);
        $this->sessionProduct($lunch, 'buttermilk', null, null, null, true);
        $this->sessionProduct($lunch, 'fresh-lime-soda', null, null, null, true);

        // Evening Snacks — snack items, some beverages
        $evening = $sessionIds['Evening Snacks']['id'];
        $this->sessionProduct($evening, 'medu-vada', null, null, null, true);
        $this->sessionProduct($evening, 'onion-bajji', null, null, null, true);
        $this->sessionProduct($evening, 'samosa', null, null, null, true);
        $this->sessionProduct($evening, 'veg-cutlet', null, null, null, true);
        $this->sessionProduct($evening, 'bread-pakora', null, null, null, true);
        $this->sessionProduct($evening, 'filter-coffee', null, null, null, true);
        $this->sessionProduct($evening, 'fresh-lime-soda', null, null, null, true);
        $this->sessionProduct($evening, 'kesari', null, null, 15, true); // Limited qty for snacks
        $this->sessionProduct($evening, 'masala-dosa', 8000, null, 10, true); // Premium price in snack time

        // Dinner Tiffin — similar to lunch but different mix
        $dinner = $sessionIds['Dinner Tiffin']['id'];
        $this->sessionProduct($dinner, 'mini-tiffin', null, null, null, true);
        $this->sessionProduct($dinner, 'standard-tiffin', null, null, null, true);
        $this->sessionProduct($dinner, 'premium-thali', null, null, null, true);
        $this->sessionProduct($dinner, 'veg-biryani', null, null, null, true);
        $this->sessionProduct($dinner, 'paneer-butter-masala', null, null, null, true);
        $this->sessionProduct($dinner, 'curd-rice', null, null, null, true);
        $this->sessionProduct($dinner, 'idli', null, null, null, true); // Also available at dinner
        $this->sessionProduct($dinner, 'masala-dosa', null, null, null, true);
        $this->sessionProduct($dinner, 'gulab-jamun', null, null, null, true);
        $this->sessionProduct($dinner, 'buttermilk', null, null, null, true);
        $this->sessionProduct($dinner, 'mango-lassi', null, null, 10, true); // Limited

        // Sunday Special Brunch — premium selection, special pricing
        $brunch = $sessionIds['Sunday Special Brunch']['id'];
        $this->sessionProduct($brunch, 'premium-thali', 22000, null, 30, true); // ₹220 special price
        $this->sessionProduct($brunch, 'veg-biryani', 16000, null, null, true); // Premium brunch price
        $this->sessionProduct($brunch, 'masala-dosa', null, null, null, true);
        $this->sessionProduct($brunch, 'idli', null, null, null, true);
        $this->sessionProduct($brunch, 'pongal', null, null, null, true);
        $this->sessionProduct($brunch, 'poori-masala', null, null, null, true);
        $this->sessionProduct($brunch, 'paneer-butter-masala', 18000, null, null, true); // Premium
        $this->sessionProduct($brunch, 'kesari', null, 30, null, true); // Higher qty limit for brunch
        $this->sessionProduct($brunch, 'payasam', null, 25, null, true);
        $this->sessionProduct($brunch, 'gulab-jamun', null, null, null, true);
        $this->sessionProduct($brunch, 'filter-coffee', null, null, null, true);
        $this->sessionProduct($brunch, 'mango-lassi', null, null, 15, true);
        // Mark diet tiffin as unavailable on Sunday brunch (it's a cheat day!)
        $this->sessionProduct($brunch, 'diet-tiffin', null, null, null, false);
    }

    private function sessionProduct(int $sessionId, string $slug, ?int $priceOverride, ?int $qtyLimit, ?int $prepMin, bool $available): void
    {
        $productId = $this->products[$slug] ?? null;
        if ($productId === null) return;

        $this->db->execute(
            "INSERT INTO meal_session_products (meal_session_id, product_id, price_override, quantity_limit, prep_minutes, available)
             VALUES (?, ?, ?, ?, ?, ?)",
            [$sessionId, $productId, $priceOverride, $qtyLimit, $prepMin, $available ? 1 : 0]
        );
    }

    // ═══════════════════════════════════════════
    //  MEAL SESSION EXCEPTIONS (holidays)
    // ═══════════════════════════════════════════

    private function seedExceptions(array $sessionIds): void
    {
        // Upcoming Diwali — all sessions closed
        $diwaliDate = '2026-10-20';
        foreach ($sessionIds as $name => $s) {
            $this->db->execute(
                "INSERT INTO meal_session_exceptions (meal_session_id, exception_date, action, reason)
                 VALUES (?, ?, 'closed', ?)",
                [$s['id'], $diwaliDate, 'Diwali holiday — kitchen closed']
            );
        }

        // Extended cutoff for a lunch session (special event day)
        $this->db->execute(
            "INSERT INTO meal_session_exceptions (meal_session_id, exception_date, action, cutoff_override, reason)
             VALUES (?, ?, 'extended_cutoff', '11:30:00', ?)",
            [$sessionIds['Lunch Thali']['id'], '2026-10-15', 'Office event catering — extended cutoff']
        );

        echo "    Session exceptions: " . (count($sessionIds) + 1) . "\n";
    }

    // ═══════════════════════════════════════════
    //  CUSTOMER + ADDRESS + FAVOURITES
    // ═══════════════════════════════════════════

    private function seedCustomer(): int
    {
        $passwordService = new PasswordService();

        $this->db->execute(
            "INSERT INTO customers (uuid, tenant_id, name, phone, email, password_hash, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())",
            [
                Uuid::uuid4()->toString(), $this->tid,
                'Priya Krishnan', '+919876543260', 'priya@example.com',
                $passwordService->hash('Customer@123'),
            ]
        );
        $customerId = (int) $this->db->lastInsertId();

        // Home address — Coimbatore near the kitchen
        $this->db->execute(
            "INSERT INTO addresses (uuid, customer_id, tenant_id, label, recipient_name, phone,
             address_line_1, address_line_2, landmark, city, state, postal_code,
             latitude, longitude, is_default, created_at)
             VALUES (?, ?, ?, 'Home', ?, ?, ?, ?, ?, 'Coimbatore', 'Tamil Nadu', '641012',
             11.0200, 76.9600, 1, NOW())",
            [
                Uuid::uuid4()->toString(), $customerId, $this->tid,
                'Priya Krishnan', '+919876543260',
                '15, Lotus Colony, RS Puram', '2nd Floor, Flat B',
                'Near RS Puram Bus Stand',
            ]
        );
        $homeAddressId = (int) $this->db->lastInsertId();

        // Office address
        $this->db->execute(
            "INSERT INTO addresses (uuid, customer_id, tenant_id, label, recipient_name, phone,
             address_line_1, city, state, postal_code,
             latitude, longitude, is_default, created_at)
             VALUES (?, ?, ?, 'Office', ?, ?, ?, 'Coimbatore', 'Tamil Nadu', '641014',
             11.0250, 76.9700, 0, NOW())",
            [
                Uuid::uuid4()->toString(), $customerId, $this->tid,
                'Priya Krishnan', '+919876543260',
                'TCS, ELCOT SEZ, Vilankurichi Road',
            ]
        );

        // Second customer
        $this->db->execute(
            "INSERT INTO customers (uuid, tenant_id, name, phone, email, password_hash, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())",
            [
                Uuid::uuid4()->toString(), $this->tid,
                'Karthik Raman', '+919876543261', 'karthik@example.com',
                $passwordService->hash('Customer@123'),
            ]
        );
        $customer2Id = (int) $this->db->lastInsertId();

        $this->db->execute(
            "INSERT INTO addresses (uuid, customer_id, tenant_id, label, recipient_name, phone,
             address_line_1, landmark, city, state, postal_code,
             latitude, longitude, is_default, created_at)
             VALUES (?, ?, ?, 'Home', ?, ?, ?, ?, 'Coimbatore', 'Tamil Nadu', '641011',
             11.0100, 76.9500, 1, NOW())",
            [
                Uuid::uuid4()->toString(), $customer2Id, $this->tid,
                'Karthik Raman', '+919876543261',
                '88, Saibaba Colony, Gandhipuram', 'Opposite Brookefields Mall',
            ]
        );

        // Favourite sessions for customer 1
        $this->db->execute(
            "INSERT IGNORE INTO customer_favourite_sessions (customer_id, meal_session_id)
             SELECT ?, id FROM meal_sessions WHERE tenant_id = ? AND name IN ('Lunch Thali', 'Sunday Special Brunch')",
            [$customerId, $this->tid]
        );

        echo "    Customers: 2 (with addresses and favourites)\n";
        return $customerId;
    }

    // ═══════════════════════════════════════════
    //  SAMPLE ORDERS — realistic data for production dashboard
    // ═══════════════════════════════════════════

    private function seedOrders(int $customerId, array $sessionIds): void
    {
        $orderRepo = new OrderRepository($this->db);

        // Get customer's home address
        $address = $this->db->fetchOne(
            "SELECT * FROM addresses WHERE customer_id = ? AND tenant_id = ? AND is_default = 1 LIMIT 1",
            [$customerId, $this->tid]
        );

        $addressSnapshot = json_encode([
            'address_line_1' => $address['address_line_1'],
            'address_line_2' => $address['address_line_2'] ?? '',
            'landmark' => $address['landmark'] ?? '',
            'city' => $address['city'],
            'postal_code' => $address['postal_code'],
            'latitude' => $address['latitude'],
            'longitude' => $address['longitude'],
        ]);

        // Get second customer
        $customer2 = $this->db->fetchOne(
            "SELECT id FROM customers WHERE tenant_id = ? AND email = 'karthik@example.com'",
            [$this->tid]
        );
        $customer2Id = (int) $customer2['id'];
        $address2 = $this->db->fetchOne(
            "SELECT * FROM addresses WHERE customer_id = ? AND tenant_id = ? LIMIT 1",
            [$customer2Id, $this->tid]
        );
        $addressSnapshot2 = json_encode([
            'address_line_1' => $address2['address_line_1'],
            'city' => $address2['city'],
            'postal_code' => $address2['postal_code'],
            'latitude' => $address2['latitude'],
            'longitude' => $address2['longitude'],
        ]);

        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $orderCount = 0;

        // ── Yesterday's completed Lunch orders ──
        $lunchId = $sessionIds['Lunch Thali']['id'];
        $orderCount += $this->createOrder($orderRepo, $customerId, (int) $address['id'], $addressSnapshot,
            $lunchId, $yesterday, 'delivered', 'standard-tiffin', 12000, 2, 'cash_on_delivery');
        $orderCount += $this->createOrder($orderRepo, $customer2Id, (int) $address2['id'], $addressSnapshot2,
            $lunchId, $yesterday, 'delivered', 'premium-thali', 18000, 1, 'cash_on_delivery');
        $orderCount += $this->createOrder($orderRepo, $customerId, (int) $address['id'], $addressSnapshot,
            $lunchId, $yesterday, 'delivered', 'veg-biryani', 14000, 1, 'cash_on_delivery');

        // ── Yesterday's Dinner orders ──
        $dinnerId = $sessionIds['Dinner Tiffin']['id'];
        $orderCount += $this->createOrder($orderRepo, $customer2Id, (int) $address2['id'], $addressSnapshot2,
            $dinnerId, $yesterday, 'delivered', 'mini-tiffin', 7500, 3, 'cash_on_delivery');

        // ── Today's Breakfast orders (completed) ──
        $breakfastId = $sessionIds['Breakfast Tiffin']['id'];
        $orderCount += $this->createOrder($orderRepo, $customerId, (int) $address['id'], $addressSnapshot,
            $breakfastId, $today, 'delivered', 'idli', 4000, 2, 'cash_on_delivery');
        $orderCount += $this->createOrder($orderRepo, $customer2Id, (int) $address2['id'], $addressSnapshot2,
            $breakfastId, $today, 'delivered', 'masala-dosa', 7000, 1, 'cash_on_delivery');

        // ── Today's Lunch orders (in progress) ──
        $orderCount += $this->createOrder($orderRepo, $customerId, (int) $address['id'], $addressSnapshot,
            $lunchId, $today, 'confirmed', 'premium-thali', 18000, 1, 'cash_on_delivery');
        $orderCount += $this->createOrder($orderRepo, $customer2Id, (int) $address2['id'], $addressSnapshot2,
            $lunchId, $today, 'preparing', 'standard-tiffin', 12000, 2, 'cash_on_delivery');
        $orderCount += $this->createOrder($orderRepo, $customerId, (int) $address['id'], $addressSnapshot,
            $lunchId, $today, 'confirmed', 'veg-biryani', 14000, 1, 'cash_on_delivery');

        // ── Today's Dinner orders (preordered) ──
        $orderCount += $this->createOrder($orderRepo, $customer2Id, (int) $address2['id'], $addressSnapshot2,
            $dinnerId, $today, 'confirmed', 'premium-thali', 18000, 1, 'cash_on_delivery');

        echo "    Orders: {$orderCount} (across multiple sessions)\n";
    }

    private function createOrder(
        OrderRepository $orderRepo, int $customerId, int $addressId, string $addressSnapshot,
        int $sessionId, string $serviceDate, string $status,
        string $productSlug, int $unitPrice, int $qty, string $paymentMethod
    ): int {
        $productId = $this->products[$productSlug] ?? null;
        if ($productId === null) return 0;

        $product = $this->db->fetchOne("SELECT name, slug, pricing_mode, unit FROM products WHERE id = ?", [$productId]);
        $lineTotal = $unitPrice * $qty;
        $deliveryFee = 2000;
        $taxAmount = (int) round($lineTotal * 5 / 100); // 5% GST
        $total = $lineTotal + $deliveryFee + $taxAmount;

        $orderNumber = $orderRepo->generateOrderNumber($this->tid);

        $orderId = $orderRepo->create([
            'uuid' => Uuid::uuid4()->toString(),
            'order_number' => $orderNumber,
            'tenant_id' => $this->tid,
            'customer_id' => $customerId,
            'address_id' => $addressId,
            'status' => $status,
            'order_type' => 'delivery',
            'subtotal' => $lineTotal,
            'delivery_fee' => $deliveryFee,
            'service_charge' => 0,
            'tax_amount' => $taxAmount,
            'discount_amount' => 0,
            'total' => $total,
            'coupon_code' => null,
            'payment_method' => $paymentMethod,
            'payment_status' => 'cod',
            'notes' => null,
            'address_snapshot' => $addressSnapshot,
            'scheduled_at' => $serviceDate . ' 12:00:00',
            'meal_session_id' => $sessionId,
            'service_date' => $serviceDate,
        ]);

        $orderRepo->addItem([
            'order_id' => $orderId,
            'product_id' => $productId,
            'variant_id' => null,
            'product_snapshot' => json_encode([
                'name' => $product['name'], 'slug' => $product['slug'],
                'pricing_mode' => $product['pricing_mode'], 'unit' => $product['unit'],
            ]),
            'variant_snapshot' => null,
            'addons_snapshot' => null,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'addons_price' => 0,
            'line_total' => $lineTotal,
            'notes' => null,
        ]);

        $orderRepo->addStatusHistory($orderId, null, $status, 'customer', $customerId);

        return 1;
    }
}
