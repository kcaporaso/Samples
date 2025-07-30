<?php

namespace App\Message\Command;

class CreateOrderInHubSpot
{
    private int $purchaseId;

    public function __construct(int $purchaseId)
    {
        $this->purchaseId = $purchaseId;
    }

    public function getPurchaseId(): int
    {
        return $this->purchaseId;
    }
}