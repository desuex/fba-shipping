<?php

namespace Tests;

use GuzzleHttp\ClientInterface;
use App\Data\BuyerInterface;
use App\Data\Order;

class ShippingServiceTest extends \PHPUnit\Framework\TestCase
{
    public function testLoadData(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $buyer = $this->createMock(BuyerInterface::class);
        $jsonContent = file_get_contents(__DIR__ . '/../mock/order.16400.json');
        $orderData = json_decode($jsonContent, true);
        $order = new Order((int)$orderData['order_id'], $orderData);
        $this->assertEquals(16400, $order->getOrderId());
    }
}