<?php

declare(strict_types=1);

namespace App\Modules\Order\Service;

use App\Core\Database\Connection;
use App\Modules\Order\Domain\MarketplaceFee;
use Ramsey\Uuid\Uuid;

final class MarketplaceFeeAccrualService
{
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Accrue the platform fee once for a completed marketplace order.
     * The ledger's unique order constraint makes retries safe.
     */
    public function accrue(int $tenantId, int $orderId, int $grossOrderValue): void
    {
        $tenant = $this->db->fetchOne(
            'SELECT commercial_plan FROM tenants WHERE id = ?',
            [$tenantId],
        );

        if ($tenant === null || ($tenant['commercial_plan'] ?? 'branded') !== 'marketplace') {
            return;
        }

        $this->db->execute(
            'INSERT IGNORE INTO platform_fee_ledger
                (uuid, tenant_id, order_id, gross_order_value, fee_amount, fee_rule)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                Uuid::uuid4()->toString(),
                $tenantId,
                $orderId,
                $grossOrderValue,
                MarketplaceFee::calculate($grossOrderValue),
                MarketplaceFee::RULE,
            ],
        );
    }
}
