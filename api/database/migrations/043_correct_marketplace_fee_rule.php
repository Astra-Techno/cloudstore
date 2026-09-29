<?php

declare(strict_types=1);

return [
    'up' => "ALTER TABLE platform_fee_ledger
        ALTER COLUMN fee_rule SET DEFAULT 'one_percent_minimum_500_paise'",
    'down' => "ALTER TABLE platform_fee_ledger
        ALTER COLUMN fee_rule SET DEFAULT 'one_percent_capped_at_500_paise'",
];
