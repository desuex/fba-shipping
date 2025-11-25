<?php

namespace App\Data;

class ApiOrder extends AbstractOrder
{
    private array $payload;

    public function __construct(int $id, array $payload)
    {
        parent::__construct($id);
        $this->payload = $payload;
    }

    protected function loadOrderData(int $id): array
    {
        // In a real implementation, the data should be fetched from a data source.
        // Let's assume that the payload provided in the constructor is the data we need.
        return $this->payload;
    }
}