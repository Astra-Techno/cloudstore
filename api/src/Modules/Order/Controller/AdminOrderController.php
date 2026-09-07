<?php

declare(strict_types=1);

namespace App\Modules\Order\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Modules\Auth\Repository\AdminRepository;
use App\Modules\Auth\Repository\DriverRepository;
use App\Modules\Delivery\Service\DriverService;
use App\Modules\Order\Domain\OrderStatus;
use App\Modules\Order\Repository\OrderRepository;
use App\Modules\Order\Service\OrderManagementService;
use App\Modules\Payment\Service\PaymentService;
use App\Modules\Tenant\Domain\TenantContext;

final class AdminOrderController
{
    public function __construct(
        private readonly OrderRepository $orderRepo,
        private readonly OrderManagementService $orderManagement,
        private readonly AdminRepository $adminRepo,
        private readonly DriverRepository $driverRepo,
        private readonly DriverService $driverService,
        private readonly PaymentService $paymentService,
    ) {
    }

    public function list(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();
        $status = $request->query['status'] ?? null;
        $page = (int) ($request->query['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $orders = $this->orderRepo->findByTenant($tenantId, $status, $limit, $offset);
        $total = $this->orderRepo->countByTenant($tenantId, $status);

        return Response::success($orders, [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'last_page' => (int) ceil($total / $limit),
        ]);
    }

    public function board(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();

        // Status counts
        $counts = $this->orderRepo->countsByStatus($tenantId);

        // Active orders with items (last 200)
        $activeOrders = $this->orderRepo->findByTenantWithItems($tenantId);

        return Response::success([
            'counts' => $counts,
            'orders' => $activeOrders,
        ]);
    }

    public function show(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();
        $order = $this->orderRepo->findByUuid($params['uuid'], $tenantId);

        if ($order === null) {
            return Response::notFound('Order not found.');
        }

        $items = $this->orderRepo->getItems((int) $order['id']);
        $history = $this->orderRepo->getStatusHistory((int) $order['id']);

        return Response::success([
            'order' => $order,
            'items' => $items,
            'status_history' => $history,
            'allowed_transitions' => OrderStatus::getAllowedTransitions($order['status']),
        ]);
    }

    public function updateStatus(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();
        $data = $request->json();

        $validator = new Validator();
        if (!$validator->validate($data, [
            'status' => ['required', 'string'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $order = $this->orderRepo->findByUuid($params['uuid'], $tenantId);
        if ($order === null) {
            return Response::notFound('Order not found.');
        }

        $admin = $this->adminRepo->findByEmail($request->authClaims['email'] ?? '', $tenantId);
        $adminId = $admin ? (int) $admin['id'] : 0;

        $result = $this->orderManagement->updateStatus(
            $tenantId,
            (int) $order['id'],
            $data['status'],
            'admin',
            $adminId,
            $data['notes'] ?? null,
        );

        if (isset($result['error'])) {
            return Response::error($result['error'], $result['code'], 422);
        }

        return Response::success($result);
    }

    public function assignDriver(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();
        $data = $request->json();

        $validator = new Validator();
        if (!$validator->validate($data, [
            'driver_uuid' => ['required', 'string'],
        ])) {
            return Response::validationError($validator->getErrors());
        }

        $order = $this->orderRepo->findByUuid($params['uuid'], $tenantId);
        if ($order === null) {
            return Response::notFound('Order not found.');
        }

        $driver = $this->driverRepo->findByUuid($data['driver_uuid']);

        if ($driver === null || (int) $driver['tenant_id'] !== $tenantId) {
            return Response::notFound('Driver not found.');
        }

        $result = $this->driverService->assignDriver($tenantId, (int) $order['id'], (int) $driver['id']);

        if (isset($result['error'])) {
            return Response::error($result['error'], $result['code'], 400);
        }

        return Response::success($result, status: 201);
    }

    public function dashboard(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();
        $stats = $this->orderManagement->getDashboardStats($tenantId);

        return Response::success($stats);
    }

    public function refund(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();
        $data = $request->json();

        $order = $this->orderRepo->findByUuid($params['uuid'], $tenantId);
        if ($order === null) {
            return Response::notFound('Order not found.');
        }

        $admin = $this->adminRepo->findByEmail($request->authClaims['email'] ?? '', $tenantId);
        $adminId = $admin ? (int) $admin['id'] : 0;

        $result = $this->paymentService->initiateRefund(
            $tenantId,
            (int) $order['id'],
            $data['reason'] ?? 'Admin initiated refund',
            'admin',
            $adminId,
        );

        if (isset($result['error'])) {
            return Response::error($result['error'], $result['code'], 400);
        }

        return Response::success($result);
    }

    public function availableDrivers(Request $request, array $params): Response
    {
        $tenantId = TenantContext::id();
        $drivers = $this->driverService->getAvailableDrivers($tenantId);

        return Response::success($drivers);
    }
}
