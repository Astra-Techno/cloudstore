<?php

declare(strict_types=1);

namespace App\Modules\Notification\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Modules\Auth\Repository\CustomerRepository;
use App\Modules\Notification\Repository\NotificationRepository;
use App\Modules\Tenant\Domain\TenantContext;

final class NotificationController
{
    public function __construct(
        private readonly NotificationRepository $notificationRepo,
        private readonly CustomerRepository $customerRepo,
    ) {
    }

    public function listCustomer(Request $request, array $params): Response
    {
        $customer = $this->customerRepo->findByUuid($request->authClaims['sub']);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $tenantId = TenantContext::id();
        $page = (int) ($request->query['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $notifications = $this->notificationRepo->findByRecipient(
            'customer', (int) $customer['id'], $tenantId, $limit, $offset
        );

        $unread = $this->notificationRepo->countUnread('customer', (int) $customer['id'], $tenantId);

        return Response::success([
            'notifications' => $notifications,
            'unread_count' => $unread,
        ]);
    }

    public function markRead(Request $request, array $params): Response
    {
        $customer = $this->customerRepo->findByUuid($request->authClaims['sub']);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $this->notificationRepo->markRead(
            (int) $params['id'],
            'customer',
            (int) $customer['id'],
        );

        return Response::success(['read' => true]);
    }

    public function markAllRead(Request $request, array $params): Response
    {
        $customer = $this->customerRepo->findByUuid($request->authClaims['sub']);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $this->notificationRepo->markAllRead('customer', (int) $customer['id'], TenantContext::id());

        return Response::success(['read_all' => true]);
    }
}
