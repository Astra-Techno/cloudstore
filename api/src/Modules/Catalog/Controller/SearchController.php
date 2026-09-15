<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Controller;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Database\Connection;
use App\Modules\Tenant\Domain\TenantContext;

final class SearchController
{
    public function __construct(
        private readonly Connection $db,
    ) {
    }

    /**
     * GET /search/suggestions?q=chi
     * Returns product and category name suggestions for typeahead.
     */
    public function suggestions(Request $request, array $params): Response
    {
        $query = trim($request->query['q'] ?? '');
        if (strlen($query) < 2) {
            return Response::success([]);
        }

        $tenantId = TenantContext::id();
        $term = '%' . $query . '%';

        $products = $this->db->fetchAll(
            "SELECT uuid, name, base_price, sale_price
             FROM products
             WHERE tenant_id = ? AND status = 'active' AND name LIKE ?
             ORDER BY name ASC
             LIMIT 8",
            [$tenantId, $term]
        );

        $categories = $this->db->fetchAll(
            "SELECT uuid, name
             FROM categories
             WHERE tenant_id = ? AND status = 'active' AND name LIKE ?
             ORDER BY name ASC
             LIMIT 4",
            [$tenantId, $term]
        );

        return Response::success([
            'products' => array_map(fn(array $row) => [
                'uuid' => $row['uuid'],
                'name' => $row['name'],
                'price' => (int) ($row['sale_price'] ?? $row['base_price']),
                'type' => 'product',
            ], $products),
            'categories' => array_map(fn(array $row) => [
                'uuid' => $row['uuid'],
                'name' => $row['name'],
                'type' => 'category',
            ], $categories),
        ]);
    }
}
