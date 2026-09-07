<?php

declare(strict_types=1);

namespace App\Modules\Order\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Modules\Auth\Repository\CustomerRepository;
use App\Modules\Order\Repository\OrderRepository;
use App\Modules\Tenant\Domain\TenantContext;

final class OrderController
{
    public function __construct(
        private readonly OrderRepository $orderRepo,
        private readonly CustomerRepository $customerRepo,
    ) {
    }

    public function list(Request $request, array $params): Response
    {
        $customer = $this->customerRepo->findByUuid($request->authClaims['sub']);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $customerId = (int) $customer['id'];
        $tenantId = TenantContext::id();
        $page = (int) ($request->query['page'] ?? 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $orders = $this->orderRepo->findByCustomer($customerId, $tenantId, $limit, $offset);

        return Response::success($orders);
    }

    public function show(Request $request, array $params): Response
    {
        $customer = $this->customerRepo->findByUuid($request->authClaims['sub']);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $customerId = (int) $customer['id'];
        $tenantId = TenantContext::id();

        $order = $this->orderRepo->findByUuid($params['uuid'], $tenantId);
        if ($order === null || (int) $order['customer_id'] !== $customerId) {
            return Response::notFound('Order not found.');
        }

        $items = $this->orderRepo->getItems((int) $order['id']);
        $history = $this->orderRepo->getStatusHistory((int) $order['id']);

        return Response::success([
            'order' => $order,
            'items' => $items,
            'status_history' => $history,
        ]);
    }
}
