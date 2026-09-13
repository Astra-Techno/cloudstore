<?php

declare(strict_types=1);

namespace App\Modules\Admin\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Database\Connection;
use App\Modules\Admin\Repository\AuditLogRepository;

final class AnalyticsController
{
    public function __construct(
        private readonly Connection $db,
        private readonly AuditLogRepository $auditLogRepo,
    ) {
    }

    /**
     * GET /admin/analytics?from=2026-01-01&to=2026-01-31
     */
    public function getAnalytics(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];

        $from = $request->query['from'] ?? date('Y-m-01');
        $to = $request->query['to'] ?? date('Y-m-d');

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            return Response::error('Invalid date format. Use YYYY-MM-DD.', 'INVALID_DATE', 400);
        }

        // Revenue and order count
        $summary = $this->db->fetchOne(
            "SELECT COUNT(*) as order_count, COALESCE(SUM(total), 0) as revenue
             FROM orders
             WHERE tenant_id = ? AND DATE(created_at) BETWEEN ? AND ?
               AND status NOT IN ('cancelled', 'rejected', 'refunded')",
            [$tenantId, $from, $to]
        );

        $orderCount = (int) ($summary['order_count'] ?? 0);
        $revenue = (int) ($summary['revenue'] ?? 0);
        $avgOrderValue = $orderCount > 0 ? (int) round($revenue / $orderCount) : 0;

        // Top 5 products by quantity sold
        $topProducts = $this->db->fetchAll(
            "SELECT oi.product_name, SUM(oi.quantity) as total_qty, SUM(oi.line_total) as total_revenue
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE o.tenant_id = ? AND DATE(o.created_at) BETWEEN ? AND ?
               AND o.status NOT IN ('cancelled', 'rejected', 'refunded')
             GROUP BY oi.product_name
             ORDER BY total_qty DESC
             LIMIT 5",
            [$tenantId, $from, $to]
        );

        // Orders by status
        $statusRows = $this->db->fetchAll(
            "SELECT status, COUNT(*) as cnt
             FROM orders
             WHERE tenant_id = ? AND DATE(created_at) BETWEEN ? AND ?
             GROUP BY status",
            [$tenantId, $from, $to]
        );

        $ordersByStatus = [];
        foreach ($statusRows as $row) {
            $ordersByStatus[$row['status']] = (int) $row['cnt'];
        }

        // Daily revenue for charting
        $dailyRevenue = $this->db->fetchAll(
            "SELECT DATE(created_at) as date,
                    COALESCE(SUM(total), 0) as revenue,
                    COUNT(*) as orders
             FROM orders
             WHERE tenant_id = ? AND DATE(created_at) BETWEEN ? AND ?
               AND status NOT IN ('cancelled', 'rejected', 'refunded')
             GROUP BY DATE(created_at)
             ORDER BY date ASC",
            [$tenantId, $from, $to]
        );

        // Cast numeric values
        $dailyRevenue = array_map(fn(array $row) => [
            'date' => $row['date'],
            'revenue' => (int) $row['revenue'],
            'orders' => (int) $row['orders'],
        ], $dailyRevenue);

        $topProducts = array_map(fn(array $row) => [
            'product_name' => $row['product_name'],
            'total_qty' => (int) $row['total_qty'],
            'total_revenue' => (int) $row['total_revenue'],
        ], $topProducts);

        return Response::success([
            'revenue' => $revenue,
            'order_count' => $orderCount,
            'avg_order_value' => $avgOrderValue,
            'top_products' => $topProducts,
            'orders_by_status' => $ordersByStatus,
            'daily_revenue' => $dailyRevenue,
            'period' => ['from' => $from, 'to' => $to],
        ]);
    }

    /**
     * GET /admin/audit-log?limit=50&offset=0
     */
    public function getAuditLog(Request $request, array $params): Response
    {
        $tenantId = (int) $request->authClaims['tenant_id'];
        $limit = min((int) ($request->query['limit'] ?? 50), 100);
        $offset = max((int) ($request->query['offset'] ?? 0), 0);

        $logs = $this->auditLogRepo->findByTenant($tenantId, $limit, $offset);

        // Decode JSON columns
        $logs = array_map(function (array $row) {
            $row['old_values'] = $row['old_values'] !== null ? json_decode($row['old_values'], true) : null;
            $row['new_values'] = $row['new_values'] !== null ? json_decode($row['new_values'], true) : null;
            return $row;
        }, $logs);

        return Response::success($logs, [
            'limit' => $limit,
            'offset' => $offset,
        ]);
    }
}
