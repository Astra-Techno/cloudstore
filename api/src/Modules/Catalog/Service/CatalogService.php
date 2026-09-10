<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Service;

use App\Modules\Catalog\Repository\ProductRepository;
use App\Modules\Catalog\Repository\CategoryRepository;
use App\Modules\Catalog\Repository\VariantRepository;
use App\Modules\Catalog\Repository\AddonRepository;
use Ramsey\Uuid\Uuid;

final class CatalogService
{
    public function __construct(
        private readonly CategoryRepository $categoryRepo,
        private readonly ProductRepository $productRepo,
        private readonly VariantRepository $variantRepo,
        private readonly AddonRepository $addonRepo,
    ) {
    }

    // --- Categories ---

    public function getCategoryByUuid(int $tenantId, string $uuid): ?array
    {
        return $this->categoryRepo->findByUuid($uuid, $tenantId);
    }

    public function createCategory(int $tenantId, array $data): array
    {
        $slug = $data['slug'] ?? $this->slugify($data['name']);

        $id = $this->categoryRepo->create([
            'uuid' => Uuid::uuid4()->toString(),
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'image_url' => $data['image_url'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'status' => $data['status'] ?? 'active',
        ]);

        return $this->categoryRepo->findById($id, $tenantId);
    }

    public function updateCategory(int $tenantId, string $uuid, array $data): ?array
    {
        $category = $this->categoryRepo->findByUuid($uuid, $tenantId);
        if ($category === null) {
            return null;
        }

        $this->categoryRepo->update((int) $category['id'], $tenantId, $data);

        return $this->categoryRepo->findById((int) $category['id'], $tenantId);
    }

    public function getCategories(int $tenantId, ?string $status = 'active'): array
    {
        $categories = $this->categoryRepo->findAll($tenantId, $status);

        foreach ($categories as &$cat) {
            $cat['product_count'] = $this->categoryRepo->getProductCount((int) $cat['id'], $tenantId);
        }

        return $categories;
    }

    public function getProductsByCategoryId(int $tenantId, int $categoryId): array
    {
        return $this->productRepo->findByCategoryId($categoryId, $tenantId);
    }

    public function deleteCategory(int $id, int $tenantId): void
    {
        $this->categoryRepo->delete($id, $tenantId);
    }

    // --- Products ---

    public function createProduct(int $tenantId, array $data): array
    {
        $slug = $data['slug'] ?? $this->slugify($data['name']);

        $id = $this->productRepo->create(array_merge($data, [
            'uuid' => Uuid::uuid4()->toString(),
            'tenant_id' => $tenantId,
            'slug' => $slug,
        ]));

        return $this->productRepo->findById($id, $tenantId);
    }

    public function updateProduct(int $tenantId, string $uuid, array $data): ?array
    {
        $product = $this->productRepo->findByUuid($uuid, $tenantId);
        if ($product === null) {
            return null;
        }

        $this->productRepo->update((int) $product['id'], $tenantId, $data);

        return $this->productRepo->findById((int) $product['id'], $tenantId);
    }

    public function getProduct(int $tenantId, string $uuid): ?array
    {
        $product = $this->productRepo->findByUuid($uuid, $tenantId);
        if ($product === null) {
            return null;
        }

        $product['variants'] = $this->variantRepo->findByProduct((int) $product['id']);
        $product['addon_groups'] = $this->addonRepo->getGroupsWithItemsForProduct((int) $product['id']);

        return $product;
    }

    public function getProducts(int $tenantId, int $page = 1, int $perPage = 50, ?string $status = null, ?string $search = null, bool $includeVariants = false): array
    {
        $offset = ($page - 1) * $perPage;
        $products = $this->productRepo->findAll($tenantId, $perPage, $offset, $status, $search);
        $total = $this->productRepo->count($tenantId, $status, $search);

        if ($includeVariants) {
            foreach ($products as &$product) {
                $product['variants'] = $this->variantRepo->findByProduct((int) $product['id']);
            }
        }

        return [
            'items' => $products,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage) ?: 1,
            ],
        ];
    }

    public function getProductsByCategory(int $tenantId, string $categoryUuid): array
    {
        $category = $this->categoryRepo->findByUuid($categoryUuid, $tenantId);
        if ($category === null) {
            return [];
        }

        return $this->productRepo->findByCategory((int) $category['id'], $tenantId);
    }

    public function deleteProduct(int $id, int $tenantId): void
    {
        $this->productRepo->delete($id, $tenantId);
    }

    // --- Variants ---

    public function createVariant(int $tenantId, string $productUuid, array $data): ?array
    {
        $product = $this->productRepo->findByUuid($productUuid, $tenantId);
        if ($product === null) {
            return null;
        }

        $id = $this->variantRepo->create(array_merge($data, [
            'uuid' => Uuid::uuid4()->toString(),
            'product_id' => $product['id'],
        ]));

        return $this->variantRepo->findById($id);
    }

    // --- Addon Groups ---

    public function getAddonGroups(int $tenantId): array
    {
        $groups = $this->addonRepo->findGroupsByTenant($tenantId);
        foreach ($groups as &$group) {
            $group['items'] = $this->addonRepo->findItemsByGroup((int) $group['id']);
        }

        return $groups;
    }

    public function createAddonGroup(int $tenantId, array $data): array
    {
        $id = $this->addonRepo->createGroup([
            'uuid' => Uuid::uuid4()->toString(),
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'is_required' => $data['is_required'] ?? 0,
            'min_selections' => $data['min_selections'] ?? 0,
            'max_selections' => $data['max_selections'] ?? 5,
            'sort_order' => $data['sort_order'] ?? 0,
            'status' => $data['status'] ?? 'active',
        ]);

        $group = $this->addonRepo->findGroupById($id, $tenantId);
        $group['items'] = [];

        return $group;
    }

    public function updateAddonGroup(int $tenantId, int $groupId, array $data): ?array
    {
        $group = $this->addonRepo->findGroupById($groupId, $tenantId);
        if ($group === null) {
            return null;
        }

        $this->addonRepo->updateGroup($groupId, $data);

        $updated = $this->addonRepo->findGroupById($groupId, $tenantId);
        $updated['items'] = $this->addonRepo->findItemsByGroup($groupId);

        return $updated;
    }

    public function addAddonItem(int $tenantId, int $groupId, array $data): ?array
    {
        $group = $this->addonRepo->findGroupById($groupId, $tenantId);
        if ($group === null) {
            return null;
        }

        $id = $this->addonRepo->createItem([
            'uuid' => Uuid::uuid4()->toString(),
            'group_id' => $groupId,
            'name' => $data['name'],
            'price' => $data['price'],
            'sort_order' => $data['sort_order'] ?? 0,
            'status' => $data['status'] ?? 'active',
        ]);

        return $this->addonRepo->findItemById($id);
    }

    public function deleteAddonItem(int $tenantId, int $groupId, int $itemId): bool
    {
        $group = $this->addonRepo->findGroupById($groupId, $tenantId);
        if ($group === null) {
            return false;
        }

        return $this->addonRepo->deleteItem($itemId, $groupId);
    }

    public function attachAddonToProduct(int $productId, int $addonGroupId): void
    {
        $this->addonRepo->attachGroupToProduct($productId, $addonGroupId);
    }

    public function detachAddonFromProduct(int $productId, int $addonGroupId): void
    {
        $this->addonRepo->detachGroupFromProduct($productId, $addonGroupId);
    }

    // --- Variant management ---

    public function updateVariant(int $tenantId, string $productUuid, int $variantId, array $data): ?array
    {
        $product = $this->productRepo->findByUuid($productUuid, $tenantId);
        if ($product === null) {
            return null;
        }

        $variant = $this->variantRepo->findById($variantId);
        if ($variant === null || (int) $variant['product_id'] !== (int) $product['id']) {
            return null;
        }

        $this->variantRepo->update($variantId, $data);

        return $this->variantRepo->findById($variantId);
    }

    public function deleteVariant(int $tenantId, string $productUuid, int $variantId): bool
    {
        $product = $this->productRepo->findByUuid($productUuid, $tenantId);
        if ($product === null) {
            return false;
        }

        $variant = $this->variantRepo->findById($variantId);
        if ($variant === null || (int) $variant['product_id'] !== (int) $product['id']) {
            return false;
        }

        $this->variantRepo->delete($variantId);

        return true;
    }

    // --- Pricing ---

    /**
     * Get the effective price for a product/variant in minor units.
     */
    public function getEffectivePrice(array $product, ?array $variant = null): int
    {
        if ($variant !== null) {
            return (int) $variant['price'];
        }

        $salePrice = $product['sale_price'] ?? null;
        if ($salePrice !== null && (int) $salePrice > 0) {
            return (int) $salePrice;
        }

        return (int) $product['base_price'];
    }

    /**
     * Calculate price for weight-based products.
     * $weightGrams: customer-selected weight in grams
     * Returns price in minor units.
     */
    public function calculateWeightPrice(array $product, int $weightGrams): int
    {
        // base_price is per-kg in minor units
        $pricePerKg = (int) $product['base_price'];

        return (int) round($pricePerKg * $weightGrams / 1000);
    }

    // --- Public catalog ---

    public function getPublicCatalog(int $tenantId): array
    {
        $categories = $this->categoryRepo->findAll($tenantId, 'active');
        $catalog = [];

        foreach ($categories as $category) {
            $products = $this->productRepo->findByCategory((int) $category['id'], $tenantId, 'active');

            foreach ($products as &$product) {
                $product['variants'] = $this->variantRepo->findByProduct((int) $product['id']);
                $product['addon_groups'] = $this->addonRepo->getGroupsWithItemsForProduct((int) $product['id']);
            }

            $catalog[] = [
                'category' => $category,
                'products' => $products,
            ];
        }

        return $catalog;
    }

    private function slugify(string $text): string
    {
        $slug = strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        return trim($slug, '-');
    }
}
