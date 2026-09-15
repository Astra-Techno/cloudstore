<?php

declare(strict_types=1);

namespace App\Modules\Customer\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Validation\Validator;
use App\Modules\Auth\Repository\AdminRepository;
use App\Modules\Auth\Repository\CustomerRepository;
use App\Modules\Customer\Repository\SupportTicketRepository;
use App\Modules\Order\Repository\OrderRepository;
use App\Modules\Tenant\Domain\TenantContext;
use Ramsey\Uuid\Uuid;

final class SupportTicketController
{
    public function __construct(
        private readonly SupportTicketRepository $tickets,
        private readonly CustomerRepository $customers,
        private readonly AdminRepository $admins,
        private readonly OrderRepository $orders,
    ) {}

    public function listCustomer(Request $request, array $params): Response
    {
        $customer = $this->customer($request);
        return $customer === null
            ? Response::unauthorized()
            : Response::success($this->tickets->findByCustomer(TenantContext::id(), (int) $customer['id']));
    }

    public function create(Request $request, array $params): Response
    {
        $customer = $this->customer($request);
        if ($customer === null) return Response::unauthorized();
        $data = $request->json();
        $validator = new Validator();
        if (!$validator->validate($data, [
            'subject' => ['required', 'string', 'min:3', 'max:180'],
            'message' => ['required', 'string', 'min:3', 'max:4000'],
            'category' => ['string', 'in:order,delivery,refund,account,other'],
            'priority' => ['string', 'in:normal,high'],
            'order_uuid' => ['string'],
        ])) return Response::validationError($validator->getErrors());

        $tenantId = TenantContext::id();
        $orderId = null;
        if (!empty($data['order_uuid'])) {
            $order = $this->orders->findByUuid((string) $data['order_uuid'], $tenantId);
            if ($order === null || (int) $order['customer_id'] !== (int) $customer['id']) {
                return Response::validationError(['order_uuid' => ['Select one of your own orders.']]);
            }
            $orderId = (int) $order['id'];
        }

        $ticketUuid = Uuid::uuid4()->toString();
        $ticketId = $this->tickets->create([
            'uuid' => $ticketUuid,
            'tenant_id' => $tenantId,
            'customer_id' => (int) $customer['id'],
            'order_id' => $orderId,
            'subject' => trim((string) $data['subject']),
            'category' => $data['category'] ?? 'other',
            'priority' => $data['priority'] ?? 'normal',
        ]);
        $this->tickets->addMessage($ticketId, Uuid::uuid4()->toString(), 'customer', (int) $customer['id'], trim((string) $data['message']));
        return Response::success(['uuid' => $ticketUuid], status: 201);
    }

    public function showCustomer(Request $request, array $params): Response
    {
        $customer = $this->customer($request);
        if ($customer === null) return Response::unauthorized();
        $ticket = $this->tickets->findForCustomer($params['uuid'], TenantContext::id(), (int) $customer['id']);
        return $ticket === null ? Response::notFound('Support request not found.') : Response::success([
            'ticket' => $ticket, 'messages' => $this->tickets->messages((int) $ticket['id']),
        ]);
    }

    public function addCustomerMessage(Request $request, array $params): Response
    {
        $customer = $this->customer($request);
        if ($customer === null) return Response::unauthorized();
        $ticket = $this->tickets->findForCustomer($params['uuid'], TenantContext::id(), (int) $customer['id']);
        if ($ticket === null) return Response::notFound('Support request not found.');
        $body = trim((string) ($request->json()['message'] ?? ''));
        if (mb_strlen($body) < 1 || mb_strlen($body) > 4000) return Response::validationError(['message' => ['Enter a message up to 4000 characters.']]);
        $this->tickets->addMessage((int) $ticket['id'], Uuid::uuid4()->toString(), 'customer', (int) $customer['id'], $body);
        return Response::success(['sent' => true]);
    }

    public function listAdmin(Request $request, array $params): Response
    {
        $status = $request->query['status'] ?? null;
        if ($status !== null && !in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true)) return Response::validationError(['status' => ['Invalid support status.']]);
        return Response::success($this->tickets->findForAdminList(TenantContext::id(), $status, min(100, max(1, (int) ($request->query['limit'] ?? 50)))));
    }

    public function showAdmin(Request $request, array $params): Response
    {
        $ticket = $this->tickets->findForAdmin($params['uuid'], TenantContext::id());
        return $ticket === null ? Response::notFound('Support request not found.') : Response::success([
            'ticket' => $ticket, 'messages' => $this->tickets->messages((int) $ticket['id']),
        ]);
    }

    public function addAdminMessage(Request $request, array $params): Response
    {
        $admin = $this->admin($request);
        if ($admin === null) return Response::unauthorized();
        $ticket = $this->tickets->findForAdmin($params['uuid'], TenantContext::id());
        if ($ticket === null) return Response::notFound('Support request not found.');
        $body = trim((string) ($request->json()['message'] ?? ''));
        if ($body === '' || mb_strlen($body) > 4000) return Response::validationError(['message' => ['Enter a message up to 4000 characters.']]);
        $this->tickets->addMessage((int) $ticket['id'], Uuid::uuid4()->toString(), 'admin', (int) $admin['id'], $body);
        return Response::success(['sent' => true]);
    }

    public function updateStatus(Request $request, array $params): Response
    {
        $ticket = $this->tickets->findForAdmin($params['uuid'], TenantContext::id());
        if ($ticket === null) return Response::notFound('Support request not found.');
        $status = (string) ($request->json()['status'] ?? '');
        if (!in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true)) return Response::validationError(['status' => ['Invalid support status.']]);
        $this->tickets->updateStatus((int) $ticket['id'], $status);
        return Response::success(['status' => $status]);
    }

    private function customer(Request $request): ?array
    {
        return isset($request->authClaims['sub']) ? $this->customers->findByUuid($request->authClaims['sub']) : null;
    }

    private function admin(Request $request): ?array
    {
        return isset($request->authClaims['sub']) ? $this->admins->findByUuid($request->authClaims['sub']) : null;
    }
}
