<?php

declare(strict_types=1);

namespace App\Modules\Notification\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Modules\Auth\Repository\AdminRepository;
use App\Modules\Notification\Repository\NotificationRepository;
use App\Modules\Tenant\Domain\TenantContext;

final class AdminNotificationController
{
    public function __construct(
        private readonly NotificationRepository $notificationRepo,
        private readonly AdminRepository $adminRepo,
    ) {
    }

    public function list(Request $request, array $params): Response
    {
        $admin = $this->adminRepo->findByUuid($request->authClaims['sub']);
        if ($admin === null) {
            return Response::unauthorized();
        }

        $tenantId = TenantContext::id();
        $page = (int) ($request->query['page'] ?? 1);
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $notifications = $this->notificationRepo->findByRecipient(
            'admin', (int) $admin['id'], $tenantId, $limit, $offset
        );

        $unread = $this->notificationRepo->countUnread('admin', (int) $admin['id'], $tenantId);

        return Response::success([
            'notifications' => $notifications,
            'unread_count' => $unread,
        ]);
    }

    public function markRead(Request $request, array $params): Response
    {
        $admin = $this->adminRepo->findByUuid($request->authClaims['sub']);
        if ($admin === null) {
            return Response::unauthorized();
        }

        $this->notificationRepo->markRead(
            (int) $params['id'],
            'admin',
            (int) $admin['id'],
        );

        return Response::success(['read' => true]);
    }

    public function markAllRead(Request $request, array $params): Response
    {
        $admin = $this->adminRepo->findByUuid($request->authClaims['sub']);
        if ($admin === null) {
            return Response::unauthorized();
        }

        $this->notificationRepo->markAllRead('admin', (int) $admin['id'], TenantContext::id());

        return Response::success(['read_all' => true]);
    }
}
