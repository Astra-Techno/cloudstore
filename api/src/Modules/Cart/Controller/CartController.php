<?php

declare(strict_types=1);

namespace App\Modules\Cart\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Modules\Auth\Repository\CustomerRepository;
use App\Modules\Cart\Repository\CartRepository;
use App\Modules\Catalog\Domain\PricingCalculator;
use App\Modules\Catalog\Repository\ProductRepository;
use App\Modules\Catalog\Repository\VariantRepository;
use App\Modules\Tenant\Domain\TenantContext;
use Ramsey\Uuid\Uuid;

final class CartController
{
    public function __construct(
        private readonly CartRepository $cartRepo,
        private readonly ProductRepository $productRepo,
        private readonly VariantRepository $variantRepo,
        private readonly CustomerRepository $customerRepo,
    ) {
    }

    public function get(Request $request, array $params): Response
    {
        $customer = $this->resolveCustomer($request);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $customerId = (int) $customer['id'];
        $tenantId = TenantContext::id();

        $cart = $this->cartRepo->findActiveByCustomer($customerId, $tenantId);
        if ($cart === null) {
            return Response::success(['cart' => null, 'items' => []]);
        }

        $items = $this->cartRepo->getItems((int) $cart['id']);

        return Response::success(['cart' => $cart, 'items' => $items]);
    }

    public function addItem(Request $request, array $params): Response
    {
        $customer = $this->resolveCustomer($request);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $customerId = (int) $customer['id'];
        $tenantId = TenantContext::id();
        $data = $request->json();

        $validator = new Validator();
        if (!$validator->validate($data, [
            'product_uuid' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $product = $this->productRepo->findByUuid($data['product_uuid'], $tenantId);
        if ($product === null || $product['status'] !== 'active') {
            return Response::notFound('Product not found or unavailable.');
        }

        $variant = null;
        if (!empty($data['variant_uuid'])) {
            $variant = $this->variantRepo->findByUuid($data['variant_uuid']);
            if ($variant === null || $variant['status'] !== 'active' || (int) $variant['product_id'] !== (int) $product['id']) {
                return Response::notFound('Variant not found or unavailable.');
            }
        }

        $unitPrice = PricingCalculator::getEffectivePrice(
            ['base_price' => $product['base_price'], 'sale_price' => $product['sale_price']],
            $variant ? ['price' => $variant['price']] : null,
        );

        // Get or create active cart
        $cart = $this->cartRepo->findActiveByCustomer($customerId, $tenantId);
        if ($cart === null) {
            $cartId = $this->cartRepo->create([
                'uuid' => Uuid::uuid4()->toString(),
                'tenant_id' => $tenantId,
                'customer_id' => $customerId,
            ]);
        } else {
            $cartId = (int) $cart['id'];
        }

        $addonsJson = !empty($data['addons']) ? json_encode($data['addons']) : null;
        $addonsPrice = (int) ($data['addons_price'] ?? 0);

        $this->cartRepo->addItem([
            'cart_id' => $cartId,
            'product_id' => (int) $product['id'],
            'variant_id' => $variant ? (int) $variant['id'] : null,
            'quantity' => (int) $data['quantity'],
            'unit_price' => $unitPrice,
            'addons_json' => $addonsJson,
            'addons_price' => $addonsPrice,
            'notes' => $data['notes'] ?? null,
        ]);

        $items = $this->cartRepo->getItems($cartId);

        return Response::success(['cart_id' => $cartId, 'items' => $items], status: 201);
    }

    public function updateItem(Request $request, array $params): Response
    {
        $customer = $this->resolveCustomer($request);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $customerId = (int) $customer['id'];
        $tenantId = TenantContext::id();
        $data = $request->json();

        $validator = new Validator();
        if (!$validator->validate($data, [
            'quantity' => ['required', 'integer', 'min:1'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $cart = $this->cartRepo->findActiveByCustomer($customerId, $tenantId);
        if ($cart === null) {
            return Response::notFound('No active cart.');
        }

        $itemId = (int) $params['itemId'];
        $this->cartRepo->updateItemQuantity($itemId, (int) $cart['id'], (int) $data['quantity']);

        $items = $this->cartRepo->getItems((int) $cart['id']);

        return Response::success(['items' => $items]);
    }

    public function removeItem(Request $request, array $params): Response
    {
        $customer = $this->resolveCustomer($request);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $customerId = (int) $customer['id'];
        $tenantId = TenantContext::id();

        $cart = $this->cartRepo->findActiveByCustomer($customerId, $tenantId);
        if ($cart === null) {
            return Response::notFound('No active cart.');
        }

        $itemId = (int) $params['itemId'];
        $this->cartRepo->removeItem($itemId, (int) $cart['id']);

        $items = $this->cartRepo->getItems((int) $cart['id']);

        return Response::success(['items' => $items]);
    }

    public function clear(Request $request, array $params): Response
    {
        $customer = $this->resolveCustomer($request);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $customerId = (int) $customer['id'];
        $tenantId = TenantContext::id();

        $cart = $this->cartRepo->findActiveByCustomer($customerId, $tenantId);
        if ($cart === null) {
            return Response::notFound('No active cart.');
        }

        $this->cartRepo->clearCart((int) $cart['id']);

        return Response::success(['cleared' => true]);
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
