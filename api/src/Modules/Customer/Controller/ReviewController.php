<?php

declare(strict_types=1);

namespace App\Modules\Customer\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Modules\Auth\Repository\CustomerRepository;
use App\Modules\Catalog\Repository\ProductRepository;
use App\Modules\Customer\Repository\ReviewRepository;
use App\Modules\Tenant\Domain\TenantContext;
use Ramsey\Uuid\Uuid;

final class ReviewController
{
    public function __construct(
        private readonly ReviewRepository $reviewRepo,
        private readonly ProductRepository $productRepo,
        private readonly CustomerRepository $customerRepo,
    ) {
    }

    /**
     * GET /products/{uuid}/reviews — public, returns reviews with customer first name.
     */
    public function listByProduct(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();

        $product = $this->productRepo->findByUuid($params['uuid'], $tenantId);
        if ($product === null) {
            return Response::notFound('Product not found.');
        }

        $limit = min((int) ($request->query['limit'] ?? 20), 100);
        $offset = max((int) ($request->query['offset'] ?? 0), 0);

        $reviews = $this->reviewRepo->findByProduct($tenantId, (int) $product['id'], $limit, $offset);

        return Response::success($reviews);
    }

    /**
     * POST /customer/reviews — create a review (one per product per customer).
     */
    public function create(Request $request, array $params): Response
    {
        $customer = $this->resolveCustomer($request);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $tenantId = TenantContext::id();
        $customerId = (int) $customer['id'];
        $data = $request->json();

        $validator = new Validator();
        if (!$validator->validate($data, [
            'product_uuid' => ['required', 'string'],
            'rating' => ['required', 'integer'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $rating = (int) $data['rating'];
        if ($rating < 1 || $rating > 5) {
            return Response::validationError(['rating' => ['Rating must be between 1 and 5.']]);
        }

        $product = $this->productRepo->findByUuid($data['product_uuid'], $tenantId);
        if ($product === null) {
            return Response::notFound('Product not found.');
        }

        $productId = (int) $product['id'];

        // One review per product per customer
        $existing = $this->reviewRepo->findByCustomerAndProduct($tenantId, $customerId, $productId);
        if ($existing !== null) {
            return Response::error('You have already reviewed this product.', 'ALREADY_REVIEWED', 409);
        }

        $reviewId = $this->reviewRepo->create([
            'uuid' => Uuid::uuid4()->toString(),
            'tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'product_id' => $productId,
            'order_id' => $data['order_id'] ?? null,
            'rating' => $rating,
            'review_text' => $data['review_text'] ?? null,
            'status' => 'approved',
        ]);

        return Response::success([
            'id' => $reviewId,
            'rating' => $rating,
            'review_text' => $data['review_text'] ?? null,
        ], status: 201);
    }

    /**
     * GET /products/{uuid}/rating — public, returns average rating and count.
     */
    public function getProductRating(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();

        $product = $this->productRepo->findByUuid($params['uuid'], $tenantId);
        if ($product === null) {
            return Response::notFound('Product not found.');
        }

        $rating = $this->reviewRepo->getAverageRating($tenantId, (int) $product['id']);

        return Response::success($rating ?? ['avg_rating' => 0, 'count' => 0]);
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
