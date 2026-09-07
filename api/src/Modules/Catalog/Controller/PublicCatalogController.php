<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Modules\Catalog\Service\CatalogService;
use App\Modules\Tenant\Domain\TenantContext;

final class PublicCatalogController
{
    public function __construct(
        private readonly CatalogService $catalogService,
    ) {
    }

    public function catalog(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();
        $catalog = $this->catalogService->getPublicCatalog($tenantId);

        return Response::success($catalog);
    }

    public function categories(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();
        $categories = $this->catalogService->getCategories($tenantId, 'active');

        return Response::success($categories);
    }

    public function productsByCategory(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();
        $products = $this->catalogService->getProductsByCategory($tenantId, $params['uuid']);

        return Response::success($products);
    }

    public function product(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();
        $product = $this->catalogService->getProduct($tenantId, $params['uuid']);

        if ($product === null) {
            return Response::notFound('Product not found.');
        }

        return Response::success($product);
    }
}
