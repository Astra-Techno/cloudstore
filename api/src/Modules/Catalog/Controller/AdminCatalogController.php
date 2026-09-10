<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Modules\Catalog\Service\CatalogService;
use App\Modules\Catalog\Service\ImageService;

final class AdminCatalogController
{
    public function __construct(
        private readonly CatalogService $catalogService,
        private readonly ImageService $imageService,
    ) {
    }

    // --- Categories ---

    public function listCategories(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $status = $request->input('status');
        $categories = $this->catalogService->getCategories($tenantId, $status);

        return Response::success($categories);
    }

    public function createCategory(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $data = $request->json();

        $validator = new Validator();
        if (!$validator->validate($data, [
            'name' => ['required', 'string', 'min:1', 'max:255'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $category = $this->catalogService->createCategory($tenantId, $data);

        return Response::success($category, status: 201);
    }

    public function updateCategory(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $data = $request->json();

        $category = $this->catalogService->updateCategory($tenantId, $params['uuid'], $data);

        if ($category === null) {
            return Response::notFound('Category not found.');
        }

        return Response::success($category);
    }

    public function deleteCategory(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $category = $this->catalogService->getCategoryByUuid($tenantId, $params['uuid']);
        if ($category === null) {
            return Response::notFound('Category not found.');
        }
        // Check if category has products
        $products = $this->catalogService->getProductsByCategoryId($tenantId, (int) $category['id']);
        if (!empty($products)) {
            return Response::error('Cannot delete category with products. Move or delete products first.', 'CATEGORY_HAS_PRODUCTS', 409);
        }
        $this->catalogService->deleteCategory((int) $category['id'], $tenantId);
        return Response::success(['deleted' => true]);
    }

    // --- Products ---

    public function listProducts(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $page = (int) ($request->input('page', 1));
        $perPage = min((int) ($request->input('per_page', 50)), 100);
        $status = $request->input('status');
        $search = $request->input('search');

        $includeVariants = $request->input('include_variants') === '1';
        $result = $this->catalogService->getProducts($tenantId, $page, $perPage, $status, $search, $includeVariants);

        return Response::success($result['items'], $result['meta']);
    }

    public function getProduct(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $product = $this->catalogService->getProduct($tenantId, $params['uuid']);

        if ($product === null) {
            return Response::notFound('Product not found.');
        }

        return Response::success($product);
    }

    public function createProduct(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $data = $request->json();

        // Resolve category_uuid to category_id if provided
        if (isset($data['category_uuid']) && !isset($data['category_id'])) {
            $category = $this->catalogService->getCategoryByUuid($tenantId, $data['category_uuid']);
            if ($category === null) {
                return Response::validationError(['category_uuid' => ['Category not found.']]);
            }
            $data['category_id'] = (int) $category['id'];
            unset($data['category_uuid']);
        }

        $validator = new Validator();
        if (!$validator->validate($data, [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'category_id' => ['required', 'integer'],
            'base_price' => ['required', 'integer'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $product = $this->catalogService->createProduct($tenantId, $data);

        return Response::success($product, status: 201);
    }

    public function updateProduct(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $data = $request->json();

        $product = $this->catalogService->updateProduct($tenantId, $params['uuid'], $data);

        if ($product === null) {
            return Response::notFound('Product not found.');
        }

        return Response::success($product);
    }

    public function deleteProduct(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $product = $this->catalogService->getProduct($tenantId, $params['uuid']);
        if ($product === null) {
            return Response::notFound('Product not found.');
        }
        $this->catalogService->deleteProduct((int) $product['id'], $tenantId);
        return Response::success(['deleted' => true]);
    }

    // --- Product Images ---

    public function listProductImages(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $product = $this->catalogService->getProduct($tenantId, $params['uuid']);
        if ($product === null) {
            return Response::notFound('Product not found.');
        }

        $images = $this->imageService->getProductImages((int) $product['id']);

        return Response::success($images);
    }

    public function uploadProductImage(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $product = $this->catalogService->getProduct($tenantId, $params['uuid']);
        if ($product === null) {
            return Response::notFound('Product not found.');
        }

        $file = $request->file('image');
        if ($file === null) {
            return Response::validationError(['image' => ['Image file is required.']]);
        }

        try {
            $isPrimary = ($request->post('is_primary', '0') === '1');
            $image = $this->imageService->uploadProductImage((int) $product['id'], $file, $isPrimary);

            return Response::success($image, status: 201);
        } catch (\RuntimeException $e) {
            return Response::error($e->getMessage(), 'UPLOAD_ERROR', 422);
        }
    }

    public function deleteProductImage(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $product = $this->catalogService->getProduct($tenantId, $params['uuid']);
        if ($product === null) {
            return Response::notFound('Product not found.');
        }

        $deleted = $this->imageService->deleteProductImage((int) $params['imageId'], (int) $product['id']);
        if (!$deleted) {
            return Response::notFound('Image not found.');
        }

        return Response::success(['deleted' => true]);
    }

    public function setPrimaryImage(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $product = $this->catalogService->getProduct($tenantId, $params['uuid']);
        if ($product === null) {
            return Response::notFound('Product not found.');
        }

        $set = $this->imageService->setPrimaryImage((int) $params['imageId'], (int) $product['id']);
        if (!$set) {
            return Response::notFound('Image not found.');
        }

        return Response::success(['primary' => true]);
    }

    // --- Addon Groups ---

    public function listAddonGroups(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $groups = $this->catalogService->getAddonGroups($tenantId);

        return Response::success($groups);
    }

    public function createAddonGroup(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $data = $request->json();

        $validator = new Validator();
        if (!$validator->validate($data, [
            'name' => ['required', 'string', 'min:1', 'max:255'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $group = $this->catalogService->createAddonGroup($tenantId, $data);

        return Response::success($group, status: 201);
    }

    public function updateAddonGroup(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $data = $request->json();

        $group = $this->catalogService->updateAddonGroup($tenantId, (int) $params['groupId'], $data);
        if ($group === null) {
            return Response::notFound('Addon group not found.');
        }

        return Response::success($group);
    }

    public function addAddonItem(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $data = $request->json();

        $validator = new Validator();
        if (!$validator->validate($data, [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'price' => ['required', 'integer'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $item = $this->catalogService->addAddonItem($tenantId, (int) $params['groupId'], $data);
        if ($item === null) {
            return Response::notFound('Addon group not found.');
        }

        return Response::success($item, status: 201);
    }

    public function deleteAddonItem(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];

        $deleted = $this->catalogService->deleteAddonItem($tenantId, (int) $params['groupId'], (int) $params['itemId']);
        if (!$deleted) {
            return Response::notFound('Addon item not found.');
        }

        return Response::success(['deleted' => true]);
    }

    public function attachAddonToProduct(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $data = $request->json();

        $product = $this->catalogService->getProduct($tenantId, $params['uuid']);
        if ($product === null) {
            return Response::notFound('Product not found.');
        }

        $this->catalogService->attachAddonToProduct((int) $product['id'], (int) ($data['addon_group_id'] ?? 0));

        return Response::success(['attached' => true]);
    }

    public function detachAddonFromProduct(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];

        $product = $this->catalogService->getProduct($tenantId, $params['uuid']);
        if ($product === null) {
            return Response::notFound('Product not found.');
        }

        $this->catalogService->detachAddonFromProduct((int) $product['id'], (int) $params['groupId']);

        return Response::success(['detached' => true]);
    }

    // --- Variants ---

    public function updateVariant(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $data = $request->json();

        $variant = $this->catalogService->updateVariant($tenantId, $params['uuid'], (int) $params['variantId'], $data);
        if ($variant === null) {
            return Response::notFound('Variant not found.');
        }

        return Response::success($variant);
    }

    public function deleteVariant(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];

        $deleted = $this->catalogService->deleteVariant($tenantId, $params['uuid'], (int) $params['variantId']);
        if (!$deleted) {
            return Response::notFound('Variant not found.');
        }

        return Response::success(['deleted' => true]);
    }

    public function createVariant(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $data = $request->json();

        $validator = new Validator();
        if (!$validator->validate($data, [
            'name' => ['required', 'string', 'min:1', 'max:255'],
            'price' => ['required', 'integer'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $variant = $this->catalogService->createVariant($tenantId, $params['uuid'], $data);

        if ($variant === null) {
            return Response::notFound('Product not found.');
        }

        return Response::success($variant, status: 201);
    }
}
