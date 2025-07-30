<?php

namespace App\Message\Command;

class UpdateOrderInHubSpot
{
    private int $purchaseId;
    private string $property;
    private string $value;

    public function __construct(int $purchaseId, string $property, string $value)
    {
        $this->purchaseId = $purchaseId;
        $this->property = $property;
        $this->value = $value;
    }

    public function getPurchaseId(): int
    {
        return $this->purchaseId;
    }

    public function getProperty(): string
    {
        return $this->property;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}