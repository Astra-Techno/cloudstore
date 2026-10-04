<?php
declare(strict_types=1);

/**
 * Seed "Annapurna Veg Kitchen" cloud kitchen tenant with full meal-session demo data.
 * Runs as a migration so it executes automatically on production deploy.
 */
return [
    'up' => function (PDO $pdo): void {
        // Skip if already seeded
        $exists = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE slug = ?");
        $exists->execute(['annapurna-veg']);
        if ((int) $exists->fetchColumn() > 0) {
            return;
        }

        $uuid = fn () => sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff),
            random_int(0, 0x0fff) | 0x4000,
            random_int(0, 0x3fff) | 0x8000,
            random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
        );

        $ins = fn (string $sql, array $params) => (function () use ($pdo, $sql, $params) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int) $pdo->lastInsertId();
        })();

        // ══════════════════════════════════════
        //  TENANT
        // ══════════════════════════════════════
        $config = json_encode([
            'delivery' => ['latitude' => 11.0168, 'longitude' => 76.9558],
            'delivery_enabled' => true,
            'pickup_enabled' => true,
            'payment_methods' => ['cod', 'razorpay'],
            'min_order_amount' => 10000,
            'tax_rate' => 5,
            'service_charge_percent' => 0,
            'delivery_charge_fixed' => 3000,
            'business_hours' => [
                'monday'    => ['open_time' => '06:00', 'close_time' => '22:00'],
                'tuesday'   => ['open_time' => '06:00', 'close_time' => '22:00'],
                'wednesday' => ['open_time' => '06:00', 'close_time' => '22:00'],
                'thursday'  => ['open_time' => '06:00', 'close_time' => '22:00'],
                'friday'    => ['open_time' => '06:00', 'close_time' => '22:00'],
                'saturday'  => ['open_time' => '06:00', 'close_time' => '22:00'],
                'sunday'    => ['open_time' => '08:00', 'close_time' => '14:00'],
            ],
        ]);

        $tenantUuid = $uuid();
        $tid = $ins(
            "INSERT INTO tenants (uuid, name, slug, business_type, status, timezone, currency, locale, contact_phone, contact_email, address, configuration)
             VALUES (?, 'Annapurna Veg Kitchen', 'annapurna-veg', 'cloud_kitchen', 'active', 'Asia/Kolkata', 'INR', 'en_IN', '+919876543250', 'annapurna@example.com', '42 Gandhi Nagar, Coimbatore', ?)",
            [$tenantUuid, $config]
        );

        // Branding
        $ins("INSERT INTO tenant_branding (tenant_id, primary_color, secondary_color, font) VALUES (?, '#059669', '#065F46', 'Nunito')
              ON DUPLICATE KEY UPDATE primary_color = VALUES(primary_color)", [$tid]);

        // Capabilities
        $capabilities = ['catalog', 'orders', 'delivery', 'payments', 'notifications', 'scheduled_order', 'meal_sessions', 'limited_quantity'];
        foreach ($capabilities as $cap) {
            $ins("INSERT INTO tenant_capabilities (tenant_id, capability, enabled) VALUES (?, ?, 1)", [$tid, $cap]);
        }

        // App token
        $plainToken = bin2hex(random_bytes(32));
        $hash = hash('sha256', $plainToken);
        $prefix = substr($plainToken, 0, 8);
        $ins("INSERT INTO app_tokens (tenant_id, token_hash, token_prefix, status) VALUES (?, ?, ?, 'active')",
            [$tid, $hash, $prefix]);

        // Admin
        $adminHash = password_hash('Admin@123', PASSWORD_ARGON2ID);
        $adminUuid = $uuid();
        $adminId = $ins(
            "INSERT INTO admins (uuid, tenant_id, name, email, password_hash, role, status) VALUES (?, ?, 'Annapurna Admin', 'annapurna-veg@cloudstore.com', ?, 'tenant_owner', 'active')",
            [$adminUuid, $tid, $adminHash]
        );
        $perms = ['catalog.manage', 'orders.manage', 'customers.view', 'settings.manage', 'analytics.view', 'delivery.manage', 'offers.manage', 'meal_sessions.manage'];
        foreach ($perms as $p) {
            $ins("INSERT INTO admin_permissions (admin_id, permission) VALUES (?, ?)", [$adminId, $p]);
        }

        // ══════════════════════════════════════
        //  ADDON GROUPS
        // ══════════════════════════════════════
        $spiceGroup = $ins("INSERT INTO addon_groups (uuid, tenant_id, name, is_required, min_selections, max_selections) VALUES (?, ?, 'Spice Level', 1, 1, 1)", [$uuid(), $tid]);
        foreach (['Mild', 'Medium', 'Spicy'] as $i => $name) {
            $ins("INSERT INTO addon_items (uuid, group_id, name, price, sort_order) VALUES (?, ?, ?, 0, ?)", [$uuid(), $spiceGroup, $name, $i]);
        }

        $sidesGroup = $ins("INSERT INTO addon_groups (uuid, tenant_id, name, is_required, min_selections, max_selections) VALUES (?, ?, 'Extra Sides', 0, 0, 3)", [$uuid(), $tid]);
        foreach (['Raita' => 2000, 'Papad (2 pcs)' => 1500, 'Pickle' => 1000, 'Extra Sambar' => 2500] as $name => $price) {
            $ins("INSERT INTO addon_items (uuid, group_id, name, price, sort_order) VALUES (?, ?, ?, ?, 0)", [$uuid(), $sidesGroup, $name, $price]);
        }

        $beverageAddon = $ins("INSERT INTO addon_groups (uuid, tenant_id, name, is_required, min_selections, max_selections) VALUES (?, ?, 'Add a Drink', 0, 0, 1)", [$uuid(), $tid]);
        foreach (['Buttermilk' => 2000, 'Filter Coffee' => 3000, 'Fresh Lime Soda' => 3500] as $name => $price) {
            $ins("INSERT INTO addon_items (uuid, group_id, name, price, sort_order) VALUES (?, ?, ?, ?, 0)", [$uuid(), $beverageAddon, $name, $price]);
        }

        // ══════════════════════════════════════
        //  CATEGORIES & PRODUCTS
        // ══════════════════════════════════════
        $mkCat = fn (string $name, string $slug, int $sort, string $desc) =>
            $ins("INSERT INTO categories (uuid, tenant_id, name, slug, sort_order, description, image_url)
                  VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$uuid(), $tid, $name, $slug, $sort, $desc, "https://placehold.co/400x300/059669/white?text=" . urlencode($name)]);

        $products = []; // slug => id
        $mkProd = function (int $catId, string $name, string $slug, int $price, string $desc, string $unit, string $type, int $prep, bool $feat, string $stockMode = 'unlimited', ?int $stockQty = null) use ($ins, $uuid, $tid, &$products) {
            $id = $ins(
                "INSERT INTO products (uuid, tenant_id, category_id, name, slug, description, base_price, pricing_mode, unit, product_type, preparation_time_minutes, is_featured, stock_mode, stock_quantity)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'fixed', ?, ?, ?, ?, ?, ?)",
                [$uuid(), $tid, $catId, $name, $slug, $desc, $price, $unit, $type, $prep, $feat ? 1 : 0, $stockMode, $stockQty]
            );
            $label = urlencode(str_replace('-', ' ', $slug));
            $ins("INSERT INTO product_images (product_id, url, alt_text, sort_order, is_primary) VALUES (?, ?, ?, 0, 1)",
                [$id, "https://placehold.co/400x300/059669/white?text={$label}", $slug]);
            $products[$slug] = $id;
            return $id;
        };
        $mkVar = fn (int $pid, string $name, int $price, int $sort, ?int $wt = null) =>
            $ins("INSERT INTO product_variants (uuid, product_id, name, price, weight_grams, sort_order) VALUES (?, ?, ?, ?, ?, ?)",
                [$uuid(), $pid, $name, $price, $wt, $sort]);
        $attach = fn (int $pid, int $gid) =>
            $pdo->prepare("INSERT INTO product_addon_groups (product_id, addon_group_id) VALUES (?, ?)")->execute([$pid, $gid]);

        // Category 1: Tiffin Boxes
        $c = $mkCat('Tiffin Boxes', 'tiffin-boxes', 1, 'Complete meal boxes packed fresh for every session');
        $p = $mkProd($c, 'Mini Tiffin', 'mini-tiffin', 7500, 'Sambar rice, poriyal, rasam, curd — a light wholesome meal', 'plate', 'simple', 15, true);
        $attach($p, $sidesGroup);
        $p = $mkProd($c, 'Standard Tiffin', 'standard-tiffin', 12000, 'Rice, sambar, rasam, kootu, poriyal, curd, pickle, papad', 'plate', 'simple', 20, true);
        $attach($p, $sidesGroup); $attach($p, $beverageAddon);
        $p = $mkProd($c, 'Premium Thali', 'premium-thali', 18000, 'Full thali with 2 curries, dal, rasam, rice, puri, sweet, papad, pickle', 'plate', 'simple', 25, true);
        $attach($p, $sidesGroup); $attach($p, $beverageAddon);
        $mkProd($c, 'Diet Tiffin', 'diet-tiffin', 9500, 'Brown rice, steamed veggies, dal, salad — low oil, no ghee', 'plate', 'simple', 15, false);

        // Category 2: Rice & Curry
        $c = $mkCat('Rice & Curry', 'rice-curry', 2, 'Hearty rice dishes and traditional curries');
        $p = $mkProd($c, 'Veg Biryani', 'veg-biryani', 14000, 'Fragrant basmati rice with mixed vegetables, saffron, and dum-style cooking', 'plate', 'variable', 25, true);
        $mkVar($p, 'Regular', 14000, 0); $mkVar($p, 'Family Pack', 35000, 1);
        $attach($p, $spiceGroup); $attach($p, $sidesGroup);
        $p = $mkProd($c, 'Curd Rice', 'curd-rice', 6000, 'Cooling tempered curd rice with pomegranate and grapes', 'plate', 'variable', 10, false);
        $mkVar($p, '250g', 6000, 0, 250); $mkVar($p, '500g', 11000, 1, 500);
        $p = $mkProd($c, 'Sambar Rice', 'sambar-rice', 8000, 'Well-mixed sambar rice with ghee and papad', 'plate', 'simple', 12, false);
        $attach($p, $spiceGroup);
        $mkProd($c, 'Lemon Rice', 'lemon-rice', 7000, 'Tangy lemon rice tempered with mustard, curry leaves, and peanuts', 'plate', 'simple', 10, false);
        $mkProd($c, 'Tamarind Rice', 'tamarind-rice', 7000, 'Puliyodarai — temple-style tamarind rice', 'plate', 'simple', 10, false);
        $p = $mkProd($c, 'Paneer Butter Masala', 'paneer-butter-masala', 16000, 'Creamy tomato gravy with soft paneer cubes', 'plate', 'simple', 20, true);
        $attach($p, $spiceGroup);

        // Category 3: Breakfast
        $c = $mkCat('Breakfast', 'breakfast', 3, 'Traditional South Indian breakfast favourites');
        $p = $mkProd($c, 'Idli', 'idli', 4000, 'Soft steamed rice cakes served with sambar and chutneys', 'plate', 'variable', 10, true);
        $mkVar($p, '3 pcs', 4000, 0); $mkVar($p, '5 pcs', 6000, 1);
        $p = $mkProd($c, 'Masala Dosa', 'masala-dosa', 7000, 'Crispy crepe filled with spiced potato masala', 'plate', 'simple', 15, true);
        $attach($p, $spiceGroup);
        $mkProd($c, 'Pongal', 'pongal', 5500, 'Pepper-cumin tempered rice-lentil porridge with ghee', 'plate', 'simple', 12, false);
        $mkProd($c, 'Upma', 'upma', 4500, 'Semolina upma with vegetables and cashews', 'plate', 'simple', 10, false);
        $mkProd($c, 'Poori Masala', 'poori-masala', 6000, 'Deep-fried puffed bread with potato curry (4 pooris)', 'plate', 'simple', 15, false);
        $p = $mkProd($c, 'Mini Idli Sambar', 'mini-idli-sambar', 5000, 'Bite-sized idlis dunked in aromatic sambar', 'plate', 'variable', 10, false);
        $mkVar($p, '12 pcs', 5000, 0); $mkVar($p, '20 pcs', 8000, 1);

        // Category 4: Snacks
        $c = $mkCat('Snacks', 'snacks', 4, 'Hot evening snacks and teatime bites');
        $mkProd($c, 'Medu Vada', 'medu-vada', 5000, 'Crispy urad dal doughnuts with sambar and chutney (3 pcs)', 'plate', 'simple', 12, true);
        $p = $mkProd($c, 'Onion Bajji', 'onion-bajji', 4500, 'Sliced onion rings in spiced gram flour batter (8 pcs)', 'plate', 'simple', 10, false);
        $attach($p, $spiceGroup);
        $mkProd($c, 'Samosa', 'samosa', 3000, 'Crispy pastry with spiced potato-peas filling (2 pcs)', 'piece', 'simple', 10, true);
        $mkProd($c, 'Vegetable Cutlet', 'veg-cutlet', 5000, 'Pan-fried mixed vegetable patties (3 pcs)', 'plate', 'simple', 15, false);
        $mkProd($c, 'Bread Pakora', 'bread-pakora', 4000, 'Stuffed bread fritters with mint chutney (2 pcs)', 'plate', 'simple', 10, false);

        // Category 5: Sweets & Desserts
        $c = $mkCat('Sweets & Desserts', 'sweets-desserts', 5, 'Traditional Indian sweets made fresh daily');
        $mkProd($c, 'Kesari', 'kesari', 5000, 'Saffron semolina halwa with cashews and ghee', 'plate', 'simple', 10, true, 'limited_stock', 30);
        $mkProd($c, 'Payasam', 'payasam', 6000, 'Creamy vermicelli kheer with cardamom and dry fruits', 'glass', 'simple', 15, false, 'limited_stock', 25);
        $mkProd($c, 'Gulab Jamun', 'gulab-jamun', 4000, 'Soft milk dumplings in rose-cardamom syrup (3 pcs)', 'plate', 'simple', 8, false);

        // Category 6: Beverages
        $c = $mkCat('Beverages', 'beverages', 6, 'Refreshing drinks and traditional brews');
        $mkProd($c, 'Filter Coffee', 'filter-coffee', 3000, 'Traditional South Indian filter coffee with fresh milk', 'glass', 'simple', 5, true);
        $mkProd($c, 'Buttermilk', 'buttermilk', 2000, 'Spiced churned buttermilk with curry leaves and ginger', 'glass', 'simple', 3, false);
        $mkProd($c, 'Fresh Lime Soda', 'fresh-lime-soda', 3500, 'Sweet or salted lime soda with mint', 'glass', 'simple', 5, false);
        $mkProd($c, 'Mango Lassi', 'mango-lassi', 5000, 'Thick mango yoghurt smoothie (seasonal)', 'glass', 'simple', 5, false, 'limited_stock', 20);

        // ══════════════════════════════════════
        //  DELIVERY ZONES
        // ══════════════════════════════════════
        foreach ([
            ['Nearby (0-3 km)', 0, 3, 2000, 30000],
            ['Mid (3-6 km)', 3, 6, 4000, 50000],
            ['Extended (6-10 km)', 6, 10, 6000, null],
        ] as $i => [$zn, $min, $max, $fee, $free]) {
            $ins("INSERT INTO delivery_zones (uuid, tenant_id, name, min_distance_km, max_distance_km, fee, min_order_free_delivery, sort_order)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)", [$uuid(), $tid, $zn, $min, $max, $fee, $free, $i]);
        }

        // ══════════════════════════════════════
        //  MEAL SESSIONS
        // ══════════════════════════════════════
        $allDays = '["monday","tuesday","wednesday","thursday","friday","saturday","sunday"]';
        $weekdays = '["monday","tuesday","wednesday","thursday","friday","saturday"]';
        $sunday = '["sunday"]';

        $sessions = [];
        $mkSession = function (string $name, string $mode, string $days, string $ss, string $se, int $odo, string $oa, int $cdo, string $ca, int $max, ?int $dfo, ?int $moa, int $sort) use ($ins, $uuid, $tid, &$sessions) {
            $sid = $ins(
                "INSERT INTO meal_sessions (uuid, tenant_id, name, ordering_mode, weekdays, service_start, service_end, opens_day_offset, opens_at, cutoff_day_offset, cutoff_at, max_orders, delivery_fee_override, min_order_amount, enabled, sort_order)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)",
                [$uuid(), $tid, $name, $mode, $days, $ss, $se, $odo, $oa, $cdo, $ca, $max, $dfo, $moa, $sort]
            );
            $sessions[$name] = $sid;
            return $sid;
        };

        $mkSession('Breakfast Tiffin', 'preorder', $weekdays, '07:30:00', '09:30:00', -1, '20:00:00', 0, '06:30:00', 50, 2000, null, 1);
        $mkSession('Lunch Thali', 'both', $allDays, '12:00:00', '14:00:00', -1, '20:00:00', 0, '10:00:00', 80, null, 15000, 2);
        $mkSession('Evening Snacks', 'instant', $allDays, '16:00:00', '18:00:00', 0, '10:00:00', 0, '15:00:00', 40, null, null, 3);
        $mkSession('Dinner Tiffin', 'preorder', $weekdays, '19:30:00', '21:30:00', 0, '10:00:00', 0, '18:30:00', 60, null, 12000, 4);
        $mkSession('Sunday Special Brunch', 'preorder', $sunday, '10:00:00', '13:00:00', -2, '18:00:00', -1, '20:00:00', 30, 0, 20000, 5);

        // ══════════════════════════════════════
        //  SESSION ↔ PRODUCT ASSIGNMENTS
        // ══════════════════════════════════════
        $sp = fn (string $session, string $slug, ?int $priceOvr, ?int $qtyLim, ?int $prep, bool $avail) =>
            $pdo->prepare("INSERT INTO meal_session_products (meal_session_id, product_id, price_override, quantity_limit, prep_minutes, available) VALUES (?, ?, ?, ?, ?, ?)")
                 ->execute([$sessions[$session], $products[$slug], $priceOvr, $qtyLim, $prep, $avail ? 1 : 0]);

        // Breakfast Tiffin
        $sp('Breakfast Tiffin', 'idli', null, null, 10, true);
        $sp('Breakfast Tiffin', 'masala-dosa', null, null, 10, true);
        $sp('Breakfast Tiffin', 'pongal', null, null, 8, true);
        $sp('Breakfast Tiffin', 'upma', null, null, 8, true);
        $sp('Breakfast Tiffin', 'poori-masala', null, null, 8, true);
        $sp('Breakfast Tiffin', 'mini-idli-sambar', null, null, 8, true);
        $sp('Breakfast Tiffin', 'medu-vada', null, null, 10, true);
        $sp('Breakfast Tiffin', 'filter-coffee', null, null, null, true);
        $sp('Breakfast Tiffin', 'buttermilk', null, null, null, true);
        $sp('Breakfast Tiffin', 'kesari', 4000, 20, 5, true);

        // Lunch Thali
        $sp('Lunch Thali', 'mini-tiffin', null, null, null, true);
        $sp('Lunch Thali', 'standard-tiffin', null, null, null, true);
        $sp('Lunch Thali', 'premium-thali', null, null, null, true);
        $sp('Lunch Thali', 'diet-tiffin', null, null, null, true);
        $sp('Lunch Thali', 'veg-biryani', null, null, null, true);
        $sp('Lunch Thali', 'curd-rice', null, null, null, true);
        $sp('Lunch Thali', 'sambar-rice', null, null, null, true);
        $sp('Lunch Thali', 'lemon-rice', null, null, null, true);
        $sp('Lunch Thali', 'tamarind-rice', null, null, null, true);
        $sp('Lunch Thali', 'paneer-butter-masala', null, null, null, true);
        $sp('Lunch Thali', 'payasam', null, null, null, true);
        $sp('Lunch Thali', 'gulab-jamun', null, null, null, true);
        $sp('Lunch Thali', 'buttermilk', null, null, null, true);
        $sp('Lunch Thali', 'fresh-lime-soda', null, null, null, true);

        // Evening Snacks
        $sp('Evening Snacks', 'medu-vada', null, null, null, true);
        $sp('Evening Snacks', 'onion-bajji', null, null, null, true);
        $sp('Evening Snacks', 'samosa', null, null, null, true);
        $sp('Evening Snacks', 'veg-cutlet', null, null, null, true);
        $sp('Evening Snacks', 'bread-pakora', null, null, null, true);
        $sp('Evening Snacks', 'filter-coffee', null, null, null, true);
        $sp('Evening Snacks', 'fresh-lime-soda', null, null, null, true);
        $sp('Evening Snacks', 'kesari', null, null, 15, true);
        $sp('Evening Snacks', 'masala-dosa', 8000, null, 10, true);

        // Dinner Tiffin
        $sp('Dinner Tiffin', 'mini-tiffin', null, null, null, true);
        $sp('Dinner Tiffin', 'standard-tiffin', null, null, null, true);
        $sp('Dinner Tiffin', 'premium-thali', null, null, null, true);
        $sp('Dinner Tiffin', 'veg-biryani', null, null, null, true);
        $sp('Dinner Tiffin', 'paneer-butter-masala', null, null, null, true);
        $sp('Dinner Tiffin', 'curd-rice', null, null, null, true);
        $sp('Dinner Tiffin', 'idli', null, null, null, true);
        $sp('Dinner Tiffin', 'masala-dosa', null, null, null, true);
        $sp('Dinner Tiffin', 'gulab-jamun', null, null, null, true);
        $sp('Dinner Tiffin', 'buttermilk', null, null, null, true);
        $sp('Dinner Tiffin', 'mango-lassi', null, null, 10, true);

        // Sunday Special Brunch
        $sp('Sunday Special Brunch', 'premium-thali', 22000, null, 30, true);
        $sp('Sunday Special Brunch', 'veg-biryani', 16000, null, null, true);
        $sp('Sunday Special Brunch', 'masala-dosa', null, null, null, true);
        $sp('Sunday Special Brunch', 'idli', null, null, null, true);
        $sp('Sunday Special Brunch', 'pongal', null, null, null, true);
        $sp('Sunday Special Brunch', 'poori-masala', null, null, null, true);
        $sp('Sunday Special Brunch', 'paneer-butter-masala', 18000, null, null, true);
        $sp('Sunday Special Brunch', 'kesari', null, 30, null, true);
        $sp('Sunday Special Brunch', 'payasam', null, 25, null, true);
        $sp('Sunday Special Brunch', 'gulab-jamun', null, null, null, true);
        $sp('Sunday Special Brunch', 'filter-coffee', null, null, null, true);
        $sp('Sunday Special Brunch', 'mango-lassi', null, null, 15, true);
        $sp('Sunday Special Brunch', 'diet-tiffin', null, null, null, false); // unavailable on brunch

        // ══════════════════════════════════════
        //  SESSION EXCEPTIONS (holidays)
        // ══════════════════════════════════════
        $diwali = '2026-10-20';
        foreach ($sessions as $sid) {
            $ins("INSERT INTO meal_session_exceptions (meal_session_id, exception_date, action, reason) VALUES (?, ?, 'closed', 'Diwali holiday - kitchen closed')", [$sid, $diwali]);
        }
        $ins("INSERT INTO meal_session_exceptions (meal_session_id, exception_date, action, cutoff_override, reason) VALUES (?, '2026-10-15', 'extended_cutoff', '11:30:00', 'Office event catering - extended cutoff')",
            [$sessions['Lunch Thali']]);

        // ══════════════════════════════════════
        //  CUSTOMERS + ADDRESSES
        // ══════════════════════════════════════
        $custHash = password_hash('Customer@123', PASSWORD_ARGON2ID);

        $c1 = $ins("INSERT INTO customers (uuid, tenant_id, name, phone, email, password_hash, status) VALUES (?, ?, 'Priya Krishnan', '+919876543260', 'priya@example.com', ?, 'active')",
            [$uuid(), $tid, $custHash]);
        $a1 = $ins("INSERT INTO addresses (uuid, customer_id, tenant_id, label, recipient_name, phone, address_line_1, address_line_2, landmark, city, state, postal_code, latitude, longitude, is_default)
             VALUES (?, ?, ?, 'Home', 'Priya Krishnan', '+919876543260', '15, Lotus Colony, RS Puram', '2nd Floor, Flat B', 'Near RS Puram Bus Stand', 'Coimbatore', 'Tamil Nadu', '641012', 11.0200, 76.9600, 1)",
            [$uuid(), $c1, $tid]);

        $c2 = $ins("INSERT INTO customers (uuid, tenant_id, name, phone, email, password_hash, status) VALUES (?, ?, 'Karthik Raman', '+919876543261', 'karthik@example.com', ?, 'active')",
            [$uuid(), $tid, $custHash]);
        $a2 = $ins("INSERT INTO addresses (uuid, customer_id, tenant_id, label, recipient_name, phone, address_line_1, landmark, city, state, postal_code, latitude, longitude, is_default)
             VALUES (?, ?, ?, 'Home', 'Karthik Raman', '+919876543261', '88, Saibaba Colony, Gandhipuram', 'Opposite Brookefields Mall', 'Coimbatore', 'Tamil Nadu', '641011', 11.0100, 76.9500, 1)",
            [$uuid(), $c2, $tid]);

        // Favourite sessions
        $pdo->prepare("INSERT INTO customer_favourite_sessions (customer_id, meal_session_id) VALUES (?, ?)")->execute([$c1, $sessions['Lunch Thali']]);
        $pdo->prepare("INSERT INTO customer_favourite_sessions (customer_id, meal_session_id) VALUES (?, ?)")->execute([$c1, $sessions['Sunday Special Brunch']]);

        // ══════════════════════════════════════
        //  SAMPLE ORDERS
        // ══════════════════════════════════════
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $orderNum = 0;
        $mkOrder = function (int $custId, int $addrId, int $sessId, string $svcDate, string $status, string $slug, int $unitPrice, int $qty) use ($ins, $uuid, $tid, &$products, &$orderNum, $pdo) {
            $pid = $products[$slug];
            $prod = $pdo->prepare("SELECT name, slug, pricing_mode, unit FROM products WHERE id = ?");
            $prod->execute([$pid]);
            $row = $prod->fetch(PDO::FETCH_ASSOC);

            $line = $unitPrice * $qty;
            $del = 2000;
            $tax = (int) round($line * 5 / 100);
            $total = $line + $del + $tax;
            $orderNum++;
            $onum = 'ORD-' . date('Ymd') . '-' . $tid . '-' . strtoupper(substr(md5((string) $orderNum . microtime()), 0, 8));

            $addr = $pdo->prepare("SELECT address_line_1, address_line_2, landmark, city, postal_code, latitude, longitude FROM addresses WHERE id = ?");
            $addr->execute([$addrId]);
            $addrData = $addr->fetch(PDO::FETCH_ASSOC);
            $snap = json_encode($addrData);

            $oid = $ins(
                "INSERT INTO orders (uuid, order_number, tenant_id, customer_id, address_id, status, order_type, subtotal, delivery_fee, service_charge, tax_amount, discount_amount, total, payment_method, payment_status, address_snapshot, scheduled_at, meal_session_id, service_date)
                 VALUES (?, ?, ?, ?, ?, ?, 'delivery', ?, ?, 0, ?, 0, ?, 'cash_on_delivery', 'cod', ?, ?, ?, ?)",
                [$uuid(), $onum, $tid, $custId, $addrId, $status, $line, $del, $tax, $total, $snap, $svcDate . ' 12:00:00', $sessId, $svcDate]
            );

            $pdo->prepare(
                "INSERT INTO order_items (order_id, product_id, product_snapshot, quantity, unit_price, addons_price, line_total) VALUES (?, ?, ?, ?, ?, 0, ?)"
            )->execute([$oid, $pid, json_encode(['name' => $row['name'], 'slug' => $row['slug'], 'pricing_mode' => $row['pricing_mode'], 'unit' => $row['unit']]), $qty, $unitPrice, $line]);

            $pdo->prepare("INSERT INTO order_status_history (order_id, from_status, to_status, actor_type, actor_id) VALUES (?, NULL, ?, 'customer', ?)")
                ->execute([$oid, $status, $custId]);
        };

        // Yesterday lunch (delivered)
        $mkOrder($c1, $a1, $sessions['Lunch Thali'], $yesterday, 'delivered', 'standard-tiffin', 12000, 2);
        $mkOrder($c2, $a2, $sessions['Lunch Thali'], $yesterday, 'delivered', 'premium-thali', 18000, 1);
        $mkOrder($c1, $a1, $sessions['Lunch Thali'], $yesterday, 'delivered', 'veg-biryani', 14000, 1);
        // Yesterday dinner
        $mkOrder($c2, $a2, $sessions['Dinner Tiffin'], $yesterday, 'delivered', 'mini-tiffin', 7500, 3);
        // Today breakfast (delivered)
        $mkOrder($c1, $a1, $sessions['Breakfast Tiffin'], $today, 'delivered', 'idli', 4000, 2);
        $mkOrder($c2, $a2, $sessions['Breakfast Tiffin'], $today, 'delivered', 'masala-dosa', 7000, 1);
        // Today lunch (in progress)
        $mkOrder($c1, $a1, $sessions['Lunch Thali'], $today, 'confirmed', 'premium-thali', 18000, 1);
        $mkOrder($c2, $a2, $sessions['Lunch Thali'], $today, 'preparing', 'standard-tiffin', 12000, 2);
        $mkOrder($c1, $a1, $sessions['Lunch Thali'], $today, 'confirmed', 'veg-biryani', 14000, 1);
        // Today dinner (preordered)
        $mkOrder($c2, $a2, $sessions['Dinner Tiffin'], $today, 'confirmed', 'premium-thali', 18000, 1);
    },
    'down' => function (PDO $pdo): void {
        $tenant = $pdo->prepare("SELECT id FROM tenants WHERE slug = 'annapurna-veg'");
        $tenant->execute();
        $row = $tenant->fetch(PDO::FETCH_ASSOC);
        if (!$row) return;
        $tid = (int) $row['id'];

        // Delete in FK-safe order
        $pdo->exec("DELETE oi FROM order_items oi JOIN orders o ON oi.order_id = o.id WHERE o.tenant_id = {$tid}");
        $pdo->exec("DELETE osh FROM order_status_history osh JOIN orders o ON osh.order_id = o.id WHERE o.tenant_id = {$tid}");
        $pdo->exec("DELETE FROM orders WHERE tenant_id = {$tid}");
        $pdo->exec("DELETE cfs FROM customer_favourite_sessions cfs JOIN customers c ON cfs.customer_id = c.id WHERE c.tenant_id = {$tid}");
        $pdo->exec("DELETE FROM addresses WHERE tenant_id = {$tid}");
        $pdo->exec("DELETE FROM customers WHERE tenant_id = {$tid}");
        $pdo->exec("DELETE me FROM meal_session_exceptions me JOIN meal_sessions ms ON me.meal_session_id = ms.id WHERE ms.tenant_id = {$tid}");
        $pdo->exec("DELETE FROM meal_session_products WHERE meal_session_id IN (SELECT id FROM meal_sessions WHERE tenant_id = {$tid})");
        $pdo->exec("DELETE FROM meal_sessions WHERE tenant_id = {$tid}");
        $pdo->exec("DELETE FROM delivery_zones WHERE tenant_id = {$tid}");
        $pdo->exec("DELETE pag FROM product_addon_groups pag JOIN products p ON pag.product_id = p.id WHERE p.tenant_id = {$tid}");
        $pdo->exec("DELETE pi FROM product_images pi JOIN products p ON pi.product_id = p.id WHERE p.tenant_id = {$tid}");
        $pdo->exec("DELETE pv FROM product_variants pv JOIN products p ON pv.product_id = p.id WHERE p.tenant_id = {$tid}");
        $pdo->exec("DELETE ai FROM addon_items ai JOIN addon_groups ag ON ai.group_id = ag.id WHERE ag.tenant_id = {$tid}");
        $pdo->exec("DELETE FROM addon_groups WHERE tenant_id = {$tid}");
        $pdo->exec("DELETE FROM products WHERE tenant_id = {$tid}");
        $pdo->exec("DELETE FROM categories WHERE tenant_id = {$tid}");
        $pdo->exec("DELETE FROM admin_permissions WHERE admin_id IN (SELECT id FROM admins WHERE tenant_id = {$tid})");
        $pdo->exec("DELETE FROM admins WHERE tenant_id = {$tid}");
        $pdo->exec("DELETE FROM app_tokens WHERE tenant_id = {$tid}");
        $pdo->exec("DELETE FROM tenant_capabilities WHERE tenant_id = {$tid}");
        $pdo->exec("DELETE FROM tenant_branding WHERE tenant_id = {$tid}");
        $pdo->exec("DELETE FROM tenants WHERE id = {$tid}");
    },
];
