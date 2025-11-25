<?php

namespace App\Data;

class Order extends AbstractOrder
{
    public function __construct(int $id, array $data)
    {
        // Confideration: maybe use order_id from data instead of id
        parent::__construct($id);
        $this->data = $data;
    }

    protected function loadOrderData(int $id): array
    {
        return $this->data ?? [];
    }
}