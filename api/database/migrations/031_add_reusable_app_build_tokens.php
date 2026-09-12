<?php

declare(strict_types=1);

return [
    'up' => [
        "ALTER TABLE app_tokens ADD COLUMN token_ciphertext TEXT NULL AFTER token_hash",
    ],
    'down' => [
        "ALTER TABLE app_tokens DROP COLUMN token_ciphertext",
    ],
];
