<?php

declare(strict_types=1);

namespace App\Modules\Order\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Modules\Auth\Repository\CustomerRepository;
use App\Modules\Order\Repository\OrderRepository;
use App\Modules\Tenant\Domain\TenantContext;
use App\Modules\Tenant\Repository\TenantRepository;

final class InvoiceController
{
    public function __construct(
        private readonly OrderRepository $orderRepo,
        private readonly CustomerRepository $customerRepo,
        private readonly TenantRepository $tenantRepo,
    ) {
    }

    /**
     * GET /customer/orders/{uuid}/invoice
     * Returns a structured invoice JSON (mobile renders it; can also be used to generate PDF).
     */
    public function getInvoice(Request $request, array $params): Response
    {
        $customer = $this->customerRepo->findByUuid($request->authClaims['sub']);
        if ($customer === null) {
            return Response::unauthorized();
        }

        $tenantId = TenantContext::id();
        $order = $this->orderRepo->findByUuid($params['uuid'], $tenantId);
        if ($order === null || (int) $order['customer_id'] !== (int) $customer['id']) {
            return Response::notFound('Order not found.');
        }

        // Only completed orders get invoices
        if (!in_array($order['status'], ['delivered', 'picked_up', 'completed'], true)) {
            return Response::error('Invoice is only available for completed orders.', 'ORDER_NOT_COMPLETED', 422);
        }

        $items = $this->orderRepo->getItems((int) $order['id']);
        $tenant = $this->tenantRepo->findById($tenantId);

        $address = json_decode((string) ($order['address_snapshot'] ?? '{}'), true);

        $invoice = [
            'invoice_number' => 'INV-' . $order['order_number'],
            'order_number' => $order['order_number'],
            'date' => $order['created_at'],
            'store' => [
                'name' => $tenant['name'] ?? 'Store',
                'phone' => $tenant['phone'] ?? null,
                'email' => $tenant['email'] ?? null,
                'address' => $tenant['address'] ?? null,
                'gstin' => $tenant['gstin'] ?? null,
            ],
            'customer' => [
                'name' => $customer['name'] ?? 'Customer',
                'phone' => $customer['phone'] ?? null,
                'email' => $customer['email'] ?? null,
                'address' => $address['full_address'] ?? ($address['address_line1'] ?? null),
            ],
            'items' => array_map(fn(array $item) => [
                'name' => $item['product_name'] ?? 'Item',
                'variant' => $item['variant_name'] ?? null,
                'quantity' => (int) $item['quantity'],
                'unit_price' => (int) $item['unit_price'],
                'addons_price' => (int) ($item['addons_price'] ?? 0),
                'line_total' => (int) $item['line_total'],
            ], $items),
            'subtotal' => (int) $order['subtotal'],
            'delivery_fee' => (int) $order['delivery_fee'],
            'service_charge' => (int) ($order['service_charge'] ?? 0),
            'tax_amount' => (int) $order['tax_amount'],
            'discount_amount' => (int) ($order['discount_amount'] ?? 0),
            'coupon_code' => $order['coupon_code'] ?? null,
            'total' => (int) $order['total'],
            'payment_method' => $order['payment_method'],
            'payment_status' => $order['payment_status'],
            'order_type' => $order['order_type'],
        ];

        return Response::success($invoice);
    }
}
