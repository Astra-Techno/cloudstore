<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database\Connection;
use App\Modules\Catalog\Repository\CategoryRepository;
use App\Modules\Catalog\Repository\ProductRepository;
use App\Modules\Catalog\Repository\VariantRepository;
use App\Modules\Catalog\Repository\AddonRepository;
use Ramsey\Uuid\Uuid;

final class CatalogSeeder
{
    public function run(Connection $db): void
    {
        $catRepo = new CategoryRepository($db);
        $prodRepo = new ProductRepository($db);
        $varRepo = new VariantRepository($db);
        $addonRepo = new AddonRepository($db);

        $tenants = $db->fetchAll("SELECT id, slug FROM tenants WHERE deleted_at IS NULL ORDER BY id");

        foreach ($tenants as $tenant) {
            $tenantId = (int) $tenant['id'];

            match ($tenant['slug']) {
                'jeyam-mutton' => $this->seedJeyamMutton($tenantId, $db, $catRepo, $prodRepo, $varRepo, $addonRepo),
                'hotel-abc' => $this->seedHotelABC($tenantId, $db, $catRepo, $prodRepo, $varRepo, $addonRepo),
                'amma-home-kitchen' => $this->seedAmmaKitchen($tenantId, $db, $catRepo, $prodRepo, $varRepo, $addonRepo),
                default => null,
            };
        }
    }

    private function addImage(Connection $db, int $productId, string $filename, bool $primary = true): void
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $label = urlencode(str_replace('-', ' ', $name));
        $url = "https://placehold.co/400x300/E02424/white?text={$label}";
        $db->execute(
            "INSERT INTO product_images (product_id, url, alt_text, sort_order, is_primary) VALUES (?, ?, ?, 0, ?)",
            [$productId, $url, $name, $primary ? 1 : 0]
        );
    }

    // ── Jeyam Mutton — Premium Meat & Biryani Shop ──

    private function seedJeyamMutton(int $tid, Connection $db, CategoryRepository $catRepo, ProductRepository $prodRepo, VariantRepository $varRepo, AddonRepository $addonRepo): void
    {
        // ── Category: Biryani ──
        $biryaniCat = $catRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'name' => 'Biryani', 'slug' => 'biryani', 'sort_order' => 1,
            'description' => 'Slow-cooked dum biryani with aromatic spices',
            'image_url' => 'https://placehold.co/400x300/1a1a2e/white?text=Biryani',
        ]);

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $biryaniCat, 'name' => 'Chicken Biryani', 'slug' => 'chicken-biryani',
            'description' => 'Fragrant basmati rice layered with tender chicken pieces, slow-cooked in traditional dum style with whole spices, saffron, and caramelized onions.',
            'short_description' => 'Aromatic dum-style chicken biryani',
            'base_price' => 18000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'product_type' => 'variable', 'preparation_time_minutes' => 25, 'is_featured' => 1,
        ]);
        $this->addImage($db, $p, 'chicken-biryani.jpg');
        $varRepo->create(['uuid' => Uuid::uuid4()->toString(), 'product_id' => $p, 'name' => 'Regular', 'price' => 18000, 'sort_order' => 0]);
        $varRepo->create(['uuid' => Uuid::uuid4()->toString(), 'product_id' => $p, 'name' => 'Family Pack', 'price' => 45000, 'sort_order' => 1]);

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $biryaniCat, 'name' => 'Mutton Biryani', 'slug' => 'mutton-biryani',
            'description' => 'Premium goat meat biryani cooked with aged basmati rice, bone marrow richness, and secret house spice blend.',
            'short_description' => 'Rich mutton dum biryani with bone-in pieces',
            'base_price' => 24000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'product_type' => 'variable', 'preparation_time_minutes' => 35, 'is_featured' => 1,
        ]);
        $this->addImage($db, $p, 'chicken-biryani.jpg');
        $varRepo->create(['uuid' => Uuid::uuid4()->toString(), 'product_id' => $p, 'name' => 'Regular', 'price' => 24000, 'sort_order' => 0]);
        $varRepo->create(['uuid' => Uuid::uuid4()->toString(), 'product_id' => $p, 'name' => 'Family Pack', 'price' => 60000, 'sort_order' => 1]);

        // ── Category: Curries ──
        $curryCat = $catRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'name' => 'Curries', 'slug' => 'curries', 'sort_order' => 2,
            'description' => 'Rich gravies and dry preparations',
            'image_url' => 'https://placehold.co/400x300/1a1a2e/white?text=Curries',
        ]);

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $curryCat, 'name' => 'Butter Chicken', 'slug' => 'butter-chicken',
            'description' => 'Tender tandoori chicken simmered in a creamy tomato-butter sauce with kasuri methi and mild spices.',
            'short_description' => 'Creamy tomato-butter chicken curry',
            'base_price' => 22000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'preparation_time_minutes' => 20, 'is_featured' => 1,
        ]);
        $this->addImage($db, $p, 'butter-chicken.jpg');

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $curryCat, 'name' => 'Chicken Tikka Masala', 'slug' => 'chicken-tikka-masala',
            'description' => 'Chargrilled chicken tikka pieces tossed in a spiced onion-tomato gravy with cream.',
            'short_description' => 'Smoky tikka in rich masala gravy',
            'base_price' => 22000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'preparation_time_minutes' => 20,
        ]);
        $this->addImage($db, $p, 'chicken-tikka-masala.jpg');

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $curryCat, 'name' => 'Mutton Rogan Josh', 'slug' => 'mutton-rogan-josh',
            'description' => 'Slow-braised mutton in a Kashmiri-style gravy with aromatic whole spices, fennel, and dried ginger.',
            'short_description' => 'Kashmiri-style slow-cooked mutton',
            'base_price' => 28000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'preparation_time_minutes' => 40,
        ]);
        $this->addImage($db, $p, 'mutton-rogan-josh.jpg');

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $curryCat, 'name' => 'Fish Curry', 'slug' => 'fish-curry',
            'description' => 'Fresh seer fish simmered in tangy tamarind and coconut gravy with curry leaves.',
            'short_description' => 'Tangy South Indian fish curry',
            'base_price' => 20000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'preparation_time_minutes' => 20,
        ]);
        $this->addImage($db, $p, 'fish-curry.jpg');

        // ── Category: Starters ──
        $starterCat = $catRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'name' => 'Starters', 'slug' => 'starters', 'sort_order' => 3,
            'description' => 'Crispy, spicy appetizers to kick things off',
            'image_url' => 'https://placehold.co/400x300/1a1a2e/white?text=Starters',
        ]);

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $starterCat, 'name' => 'Paneer Tikka', 'slug' => 'paneer-tikka',
            'description' => 'Cubes of cottage cheese marinated in spiced yogurt and chargrilled with bell peppers and onions.',
            'short_description' => 'Chargrilled spiced paneer cubes',
            'base_price' => 18000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'preparation_time_minutes' => 15,
        ]);
        $this->addImage($db, $p, 'paneer-tikka.jpg');

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $starterCat, 'name' => 'Tandoori Chicken', 'slug' => 'tandoori-chicken',
            'description' => 'Half chicken marinated overnight in yogurt, red chilli, and tandoori spices, roasted in clay oven.',
            'short_description' => 'Clay-oven roasted half chicken',
            'base_price' => 25000, 'pricing_mode' => 'fixed', 'unit' => 'half',
            'preparation_time_minutes' => 25, 'is_featured' => 1,
        ]);
        $this->addImage($db, $p, 'tandoori-chicken.jpg');

        $samosaId = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $starterCat, 'name' => 'Samosa', 'slug' => 'samosa',
            'description' => 'Crispy golden pastry filled with spiced potatoes, peas, and cumin. Served with mint chutney.',
            'short_description' => 'Crispy potato-stuffed pastry (2 pcs)',
            'base_price' => 6000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'preparation_time_minutes' => 10,
        ]);
        $this->addImage($db, $samosaId, 'samosa.jpg');

        // ── Category: Breads ──
        $breadCat = $catRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'name' => 'Breads', 'slug' => 'breads', 'sort_order' => 4,
            'description' => 'Fresh-from-tandoor breads',
            'image_url' => 'https://placehold.co/400x300/1a1a2e/white?text=Breads',
        ]);

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $breadCat, 'name' => 'Butter Naan', 'slug' => 'butter-naan',
            'description' => 'Soft leavened bread baked in tandoor and brushed with melted butter.',
            'short_description' => 'Tandoor-baked buttery naan',
            'base_price' => 5000, 'pricing_mode' => 'fixed', 'unit' => 'piece',
            'preparation_time_minutes' => 8,
        ]);
        $this->addImage($db, $p, 'naan.jpg');

        $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $breadCat, 'name' => 'Garlic Naan', 'slug' => 'garlic-naan',
            'short_description' => 'Naan topped with garlic and coriander',
            'base_price' => 6000, 'pricing_mode' => 'fixed', 'unit' => 'piece',
            'preparation_time_minutes' => 8,
        ]);

        $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $breadCat, 'name' => 'Parotta', 'slug' => 'parotta',
            'short_description' => 'Flaky layered Malabar parotta',
            'base_price' => 4000, 'pricing_mode' => 'fixed', 'unit' => 'piece',
            'preparation_time_minutes' => 10,
        ]);

        // ── Category: Drinks ──
        $drinkCat = $catRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'name' => 'Drinks', 'slug' => 'drinks', 'sort_order' => 5,
            'description' => 'Refreshing beverages',
            'image_url' => 'https://placehold.co/400x300/1a1a2e/white?text=Drinks',
        ]);

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $drinkCat, 'name' => 'Mango Lassi', 'slug' => 'mango-lassi',
            'description' => 'Thick yogurt smoothie blended with Alphonso mango pulp and a hint of cardamom.',
            'short_description' => 'Creamy mango yogurt smoothie',
            'base_price' => 8000, 'pricing_mode' => 'fixed', 'unit' => 'glass',
        ]);
        $this->addImage($db, $p, 'lassi.jpg');

        $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $drinkCat, 'name' => 'Sweet Lassi', 'slug' => 'sweet-lassi',
            'short_description' => 'Chilled sweet yogurt drink',
            'base_price' => 6000, 'pricing_mode' => 'fixed', 'unit' => 'glass',
        ]);

        $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $drinkCat, 'name' => 'Masala Chai', 'slug' => 'masala-chai',
            'short_description' => 'Spiced Indian tea with ginger & cardamom',
            'base_price' => 3000, 'pricing_mode' => 'fixed', 'unit' => 'cup',
        ]);

        // ── Addon groups ──
        $sideGroup = $addonRepo->createGroup([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'name' => 'Extra Sides', 'is_required' => 0, 'max_selections' => 3,
        ]);
        $addonRepo->createItem(['uuid' => Uuid::uuid4()->toString(), 'group_id' => $sideGroup, 'name' => 'Raita', 'price' => 4000, 'sort_order' => 0]);
        $addonRepo->createItem(['uuid' => Uuid::uuid4()->toString(), 'group_id' => $sideGroup, 'name' => 'Salan', 'price' => 3000, 'sort_order' => 1]);
        $addonRepo->createItem(['uuid' => Uuid::uuid4()->toString(), 'group_id' => $sideGroup, 'name' => 'Boiled Egg', 'price' => 2000, 'sort_order' => 2]);
        $addonRepo->createItem(['uuid' => Uuid::uuid4()->toString(), 'group_id' => $sideGroup, 'name' => 'Extra Gravy', 'price' => 5000, 'sort_order' => 3]);

        $spiceGroup = $addonRepo->createGroup([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'name' => 'Spice Level', 'is_required' => 1, 'min_selections' => 1, 'max_selections' => 1,
        ]);
        $addonRepo->createItem(['uuid' => Uuid::uuid4()->toString(), 'group_id' => $spiceGroup, 'name' => 'Mild', 'price' => 0, 'sort_order' => 0]);
        $addonRepo->createItem(['uuid' => Uuid::uuid4()->toString(), 'group_id' => $spiceGroup, 'name' => 'Medium', 'price' => 0, 'sort_order' => 1]);
        $addonRepo->createItem(['uuid' => Uuid::uuid4()->toString(), 'group_id' => $spiceGroup, 'name' => 'Hot', 'price' => 0, 'sort_order' => 2]);
        $addonRepo->createItem(['uuid' => Uuid::uuid4()->toString(), 'group_id' => $spiceGroup, 'name' => 'Extra Hot', 'price' => 0, 'sort_order' => 3]);

        echo "  Seeded catalog: Jeyam Mutton (16 products, 2 addon groups, images)\n";
    }

    // ── Hotel ABC — Full-Service Indian Restaurant ──

    private function seedHotelABC(int $tid, Connection $db, CategoryRepository $catRepo, ProductRepository $prodRepo, VariantRepository $varRepo, AddonRepository $addonRepo): void
    {
        // ── Category: Thali / Meals ──
        $thaliCat = $catRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'name' => 'Thali & Meals', 'slug' => 'thali-meals', 'sort_order' => 1,
            'description' => 'Complete meal platters',
            'image_url' => 'https://placehold.co/400x300/1a1a2e/white?text=Thali',
        ]);

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $thaliCat, 'name' => 'Veg Thali', 'slug' => 'veg-thali',
            'description' => 'Complete vegetarian platter with rice, 3 curries, dal, raita, papad, pickle, and 2 rotis.',
            'short_description' => 'Full veg meal platter with 3 curries',
            'base_price' => 15000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'preparation_time_minutes' => 15, 'is_featured' => 1,
        ]);
        $this->addImage($db, $p, 'malai-kofta.jpg');

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $thaliCat, 'name' => 'Non-Veg Thali', 'slug' => 'nonveg-thali',
            'description' => 'Hearty non-veg platter with chicken curry, dal, rice, raita, papad, and 2 rotis.',
            'short_description' => 'Complete non-veg meal with chicken curry',
            'base_price' => 20000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'preparation_time_minutes' => 20,
        ]);
        $this->addImage($db, $p, 'butter-chicken.jpg');

        // ── Category: Curries ──
        $curryCat = $catRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'name' => 'Curries', 'slug' => 'curries', 'sort_order' => 2,
            'description' => 'Signature gravies and dry preparations',
            'image_url' => 'https://placehold.co/400x300/1a1a2e/white?text=Curries',
        ]);

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $curryCat, 'name' => 'Palak Paneer', 'slug' => 'palak-paneer',
            'description' => 'Fresh spinach puree with soft paneer cubes, tempered with garlic and cumin.',
            'short_description' => 'Creamy spinach with cottage cheese',
            'base_price' => 16000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'preparation_time_minutes' => 15,
        ]);
        $this->addImage($db, $p, 'palak-paneer.jpg');

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $curryCat, 'name' => 'Dal Makhani', 'slug' => 'dal-makhani',
            'description' => 'Black lentils and kidney beans slow-cooked overnight with butter, cream, and smoky spices.',
            'short_description' => 'Creamy slow-cooked black lentils',
            'base_price' => 14000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'preparation_time_minutes' => 15, 'is_featured' => 1,
        ]);
        $this->addImage($db, $p, 'dal-makhani.jpg');

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $curryCat, 'name' => 'Malai Kofta', 'slug' => 'malai-kofta',
            'description' => 'Deep-fried paneer and potato dumplings in a rich cashew-cream gravy.',
            'short_description' => 'Paneer dumplings in creamy gravy',
            'base_price' => 18000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'preparation_time_minutes' => 20,
        ]);
        $this->addImage($db, $p, 'malai-kofta.jpg');

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $curryCat, 'name' => 'Chole Bhature', 'slug' => 'chole-bhature',
            'description' => 'Spiced chickpea curry served with fluffy deep-fried bread. A North Indian classic.',
            'short_description' => 'Spicy chickpeas with fried bread',
            'base_price' => 12000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'preparation_time_minutes' => 15,
        ]);
        $this->addImage($db, $p, 'chole-bhature.jpg');

        // ── Category: South Indian ──
        $southCat = $catRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'name' => 'South Indian', 'slug' => 'south-indian', 'sort_order' => 3,
            'description' => 'Dosas, idlis, and more',
            'image_url' => 'https://placehold.co/400x300/1a1a2e/white?text=South+Indian',
        ]);

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $southCat, 'name' => 'Masala Dosa', 'slug' => 'masala-dosa',
            'description' => 'Crispy rice-lentil crepe filled with spiced potato masala, served with sambar and coconut chutney.',
            'short_description' => 'Crispy crepe with potato filling',
            'base_price' => 9000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'preparation_time_minutes' => 12, 'is_featured' => 1,
        ]);
        $this->addImage($db, $p, 'masala-dosa.jpg');

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $southCat, 'name' => 'Idli Sambar', 'slug' => 'idli-sambar',
            'description' => 'Soft steamed rice cakes served with hot sambar and coconut chutney. Light and healthy.',
            'short_description' => 'Steamed rice cakes with sambar (4 pcs)',
            'base_price' => 6000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'preparation_time_minutes' => 10,
        ]);
        $this->addImage($db, $p, 'idli-sambar.jpg');

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $southCat, 'name' => 'Medu Vada', 'slug' => 'medu-vada',
            'description' => 'Crispy urad dal fritters with a fluffy interior. Served with sambar and chutney.',
            'short_description' => 'Crispy lentil fritters (3 pcs)',
            'base_price' => 5000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'preparation_time_minutes' => 10,
        ]);
        $this->addImage($db, $p, 'vada.jpg');

        // ── Category: Biryani ──
        $biryaniCat = $catRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'name' => 'Biryani', 'slug' => 'biryani', 'sort_order' => 4,
            'description' => 'Hyderabadi-style dum biryani',
            'image_url' => 'https://placehold.co/400x300/1a1a2e/white?text=Biryani',
        ]);

        $chickenBiryani = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $biryaniCat, 'name' => 'Chicken Dum Biryani', 'slug' => 'chicken-dum-biryani',
            'description' => 'Hyderabadi-style dum biryani with tender chicken, aged basmati, fried onions, and fresh herbs.',
            'short_description' => 'Hyderabadi chicken dum biryani',
            'base_price' => 18000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'product_type' => 'variable', 'preparation_time_minutes' => 25, 'is_featured' => 1,
        ]);
        $this->addImage($db, $chickenBiryani, 'chicken-biryani.jpg');
        $varRepo->create(['uuid' => Uuid::uuid4()->toString(), 'product_id' => $chickenBiryani, 'name' => 'Regular', 'price' => 18000, 'sort_order' => 0]);
        $varRepo->create(['uuid' => Uuid::uuid4()->toString(), 'product_id' => $chickenBiryani, 'name' => 'Large', 'price' => 28000, 'sort_order' => 1]);

        $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $biryaniCat, 'name' => 'Egg Biryani', 'slug' => 'egg-biryani',
            'short_description' => 'Aromatic biryani with boiled eggs',
            'base_price' => 14000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'preparation_time_minutes' => 20,
        ]);

        // ── Category: Desserts ──
        $dessertCat = $catRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'name' => 'Desserts', 'slug' => 'desserts', 'sort_order' => 5,
            'description' => 'Sweet endings',
            'image_url' => 'https://placehold.co/400x300/1a1a2e/white?text=Desserts',
        ]);

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $dessertCat, 'name' => 'Gulab Jamun', 'slug' => 'gulab-jamun',
            'description' => 'Soft milk-solid dumplings soaked in warm rose-cardamom sugar syrup.',
            'short_description' => 'Warm milk dumplings in rose syrup (2 pcs)',
            'base_price' => 6000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
        ]);
        $this->addImage($db, $p, 'gulab-jamun.jpg');

        $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $dessertCat, 'name' => 'Rasmalai', 'slug' => 'rasmalai',
            'short_description' => 'Soft paneer discs in saffron milk',
            'base_price' => 8000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
        ]);

        // ── Category: Breads ──
        $breadCat = $catRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'name' => 'Breads', 'slug' => 'breads', 'sort_order' => 6,
            'description' => 'Fresh tandoor breads',
            'image_url' => 'https://placehold.co/400x300/1a1a2e/white?text=Breads',
        ]);

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $breadCat, 'name' => 'Butter Naan', 'slug' => 'butter-naan',
            'short_description' => 'Soft tandoori naan with butter',
            'base_price' => 4000, 'pricing_mode' => 'fixed', 'unit' => 'piece',
        ]);
        $this->addImage($db, $p, 'naan.jpg');

        $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $breadCat, 'name' => 'Tandoori Roti', 'slug' => 'tandoori-roti',
            'short_description' => 'Whole wheat bread from tandoor',
            'base_price' => 3000, 'pricing_mode' => 'fixed', 'unit' => 'piece',
        ]);

        // ── Addon: Sides for biryani ──
        $sideGroup = $addonRepo->createGroup([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'name' => 'Biryani Sides', 'is_required' => 0, 'max_selections' => 3,
        ]);
        $addonRepo->createItem(['uuid' => Uuid::uuid4()->toString(), 'group_id' => $sideGroup, 'name' => 'Mirchi Ka Salan', 'price' => 4000, 'sort_order' => 0]);
        $addonRepo->createItem(['uuid' => Uuid::uuid4()->toString(), 'group_id' => $sideGroup, 'name' => 'Raita', 'price' => 3000, 'sort_order' => 1]);
        $addonRepo->createItem(['uuid' => Uuid::uuid4()->toString(), 'group_id' => $sideGroup, 'name' => 'Extra Egg', 'price' => 2000, 'sort_order' => 2]);

        $addonRepo->attachGroupToProduct($chickenBiryani, $sideGroup);

        echo "  Seeded catalog: Hotel ABC (18 products, 6 categories, images)\n";
    }

    // ── Amma Home Kitchen — Home-style Comfort Food ──

    private function seedAmmaKitchen(int $tid, Connection $db, CategoryRepository $catRepo, ProductRepository $prodRepo, VariantRepository $varRepo, AddonRepository $addonRepo): void
    {
        // ── Category: Today's Special ──
        $todayCat = $catRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'name' => "Today's Special", 'slug' => 'todays-special', 'sort_order' => 1,
            'description' => 'Fresh home-cooked specials, limited quantity daily',
            'image_url' => 'https://placehold.co/400x300/1a1a2e/white?text=Thali',
        ]);

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $todayCat, 'name' => 'Amma Special Meals', 'slug' => 'amma-special-meals',
            'description' => 'Home-cooked thali with rice, sambar, rasam, 2 vegetables, poriyal, curd, pickle, and papad. Just like mom makes.',
            'short_description' => 'Complete home-style veg meal',
            'base_price' => 10000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'stock_mode' => 'limited_stock', 'stock_quantity' => 30,
            'preparation_time_minutes' => 10, 'is_featured' => 1,
        ]);
        $this->addImage($db, $p, 'malai-kofta.jpg');

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $todayCat, 'name' => 'Chicken Curry Rice', 'slug' => 'chicken-curry-rice',
            'description' => 'Home-style chicken curry with steamed rice, rasam, and pickle.',
            'short_description' => 'Chicken curry with rice combo',
            'base_price' => 14000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'stock_mode' => 'limited_stock', 'stock_quantity' => 20,
            'preparation_time_minutes' => 10,
        ]);
        $this->addImage($db, $p, 'butter-chicken.jpg');

        // ── Category: South Indian ──
        $southCat = $catRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'name' => 'South Indian', 'slug' => 'south-indian', 'sort_order' => 2,
            'description' => 'Traditional South Indian breakfast & snacks',
            'image_url' => 'https://placehold.co/400x300/1a1a2e/white?text=South+Indian',
        ]);

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $southCat, 'name' => 'Masala Dosa', 'slug' => 'masala-dosa',
            'description' => 'Home-style crispy dosa with potato filling, sambar, and fresh coconut chutney.',
            'short_description' => 'Crispy dosa with potato masala',
            'base_price' => 7000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'preparation_time_minutes' => 12, 'is_featured' => 1,
        ]);
        $this->addImage($db, $p, 'masala-dosa.jpg');

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $southCat, 'name' => 'Idli Sambar', 'slug' => 'idli-sambar',
            'description' => 'Soft homemade idlis with piping hot sambar and fresh coconut chutney.',
            'short_description' => 'Soft steamed idlis (4 pcs)',
            'base_price' => 5000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'preparation_time_minutes' => 8,
        ]);
        $this->addImage($db, $p, 'idli-sambar.jpg');

        $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $southCat, 'name' => 'Pongal', 'slug' => 'pongal',
            'short_description' => 'Creamy rice-lentil comfort food',
            'base_price' => 5000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'preparation_time_minutes' => 8,
        ]);

        $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $southCat, 'name' => 'Upma', 'slug' => 'upma',
            'short_description' => 'Semolina porridge with vegetables',
            'base_price' => 4000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
            'preparation_time_minutes' => 8,
        ]);

        // ── Category: Rice Items ──
        $riceCat = $catRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'name' => 'Rice Items', 'slug' => 'rice-items', 'sort_order' => 3,
            'description' => 'Flavored rice varieties',
        ]);

        $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $riceCat, 'name' => 'Lemon Rice', 'slug' => 'lemon-rice',
            'description' => 'Tangy lemon-flavored rice tempered with mustard seeds, peanuts, and curry leaves.',
            'short_description' => 'Tangy lemon rice with peanuts',
            'base_price' => 6000, 'pricing_mode' => 'fixed', 'unit' => 'pack',
            'stock_mode' => 'limited_stock', 'stock_quantity' => 15,
        ]);

        $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $riceCat, 'name' => 'Curd Rice', 'slug' => 'curd-rice',
            'short_description' => 'Cool & creamy curd rice with pomegranate',
            'base_price' => 5000, 'pricing_mode' => 'fixed', 'unit' => 'pack',
            'stock_mode' => 'limited_stock', 'stock_quantity' => 15,
        ]);

        $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $riceCat, 'name' => 'Tamarind Rice', 'slug' => 'tamarind-rice',
            'short_description' => 'Tangy tamarind rice with groundnuts',
            'base_price' => 6000, 'pricing_mode' => 'fixed', 'unit' => 'pack',
            'stock_mode' => 'limited_stock', 'stock_quantity' => 15,
        ]);

        // ── Category: Snacks ──
        $snackCat = $catRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'name' => 'Snacks', 'slug' => 'snacks', 'sort_order' => 4,
            'description' => 'Evening tea-time treats',
            'image_url' => 'https://placehold.co/400x300/1a1a2e/white?text=Starters',
        ]);

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $snackCat, 'name' => 'Pani Puri', 'slug' => 'pani-puri',
            'description' => 'Crispy hollow puris filled with spiced water, tamarind chutney, potato, and chickpeas.',
            'short_description' => 'Crispy puris with tangy water (8 pcs)',
            'base_price' => 5000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
        ]);
        $this->addImage($db, $p, 'pani-puri.jpg');

        $p = $prodRepo->create([
            'uuid' => Uuid::uuid4()->toString(), 'tenant_id' => $tid,
            'category_id' => $snackCat, 'name' => 'Samosa', 'slug' => 'samosa',
            'short_description' => 'Crispy potato samosa (2 pcs)',
            'base_price' => 4000, 'pricing_mode' => 'fixed', 'unit' => 'plate',
        ]);
        $this->addImage($db, $p, 'samosa.jpg');

        echo "  Seeded catalog: Amma Home Kitchen (13 products, 4 categories, images)\n";
    }
}
