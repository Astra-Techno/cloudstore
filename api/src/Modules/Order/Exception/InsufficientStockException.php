<?php

declare(strict_types=1);

namespace App\Modules\Order\Exception;

final class InsufficientStockException extends \RuntimeException
{
    public function __construct(
        private readonly string $productName,
    ) {
        parent::__construct('Insufficient stock.');
    }

    public function getProductName(): string
    {
        return $this->productName;
    }
}