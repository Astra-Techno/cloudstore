<?php

declare(strict_types=1);

namespace App\Modules\Customer\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Modules\Auth\Repository\CustomerRepository;
use App\Modules\Catalog\Repository\ProductRepository;
use App\Modules\Customer\Repository\FavouriteRepository;
use App\Modules\Tenant\Domain\TenantContext;

final class FavouriteController
{
    public function __construct(
        private readonly FavouriteRepository $favouriteRepo,
        private readonly ProductRepository $productRepo,
        private readonly CustomerRepository $customerRepo,
    ) {
    }

    /**
     * GET /customer/favourites — list favourite products with full product data.
     */
    public function list(Request $request, array $params): Response
    {
        $customer = $this->resolveCustomer($request);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $tenantId = TenantContext::id();
        $products = $this->favouriteRepo->findProductsByCustomer($tenantId, (int) $customer['id']);

        return Response::success($products);
    }

    /**
     * POST /customer/favourites/{uuid} — toggle favourite on/off.
     */
    public function toggle(Request $request, array $params): Response
    {
        $customer = $this->resolveCustomer($request);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $tenantId = TenantContext::id();
        $customerId = (int) $customer['id'];

        $product = $this->productRepo->findByUuid($params['uuid'], $tenantId);
        if ($product === null) {
            return Response::notFound('Product not found.');
        }

        $productId = (int) $product['id'];

        if ($this->favouriteRepo->isFavourite($tenantId, $customerId, $productId)) {
            $this->favouriteRepo->remove($tenantId, $customerId, $productId);
            $favourited = false;
        } else {
            $this->favouriteRepo->add($tenantId, $customerId, $productId);
            $favourited = true;
        }

        return Response::success(['favourited' => $favourited]);
    }

    private function resolveCustomer(Request $request): ?array
    {
        $claims = $request->authClaims ?? null;
        if ($claims === null) {
            return null;
        }

        return $this->customerRepo->findByUuid($claims['sub']);
    }
}
