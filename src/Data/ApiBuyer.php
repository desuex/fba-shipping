<?php

namespace App\Data;


class ApiBuyer implements BuyerInterface
{
    public string $name;
    private array $data;

    public function __construct(array $data)
    {
        $this->name = $data['shop_username'];
        $this->data = $data;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->data[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }
}