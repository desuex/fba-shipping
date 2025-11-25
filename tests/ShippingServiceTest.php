<?php

namespace Tests;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use App\Data\BuyerInterface;
use App\Data\AbstractOrder;
use App\Auth\AuthProviderInterface;
use App\ShippingService;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;

class ShippingServiceTest extends \PHPUnit\Framework\TestCase
{
    private string $fakeToken = 'fake-jwt-token-123';
    private array $orderData;
    private array $buyerData;

    protected function setUp(): void
    {
        parent::setUp();
        
        $orderJsonContent = file_get_contents(__DIR__ . '/../mock/order.16400.json');
        $this->orderData = json_decode($orderJsonContent, true) ?? [];

        $buyerJsonContent = file_get_contents(__DIR__ . '/../mock/buyer.29664.json');
        $this->buyerData = json_decode($buyerJsonContent, true) ?? [];
    }


    public function testShipSendsCorrectPayloadWithAuthToken(): void
    {
        $orderMock = $this->createMock(AbstractOrder::class);
        $orderMock->data = $this->orderData;

        $buyerMock = $this->createMock(BuyerInterface::class);

        $buyerMock->name = $this->buyerData['shop_username'];
        

        $authMock = $this->createMock(AuthProviderInterface::class);
        $authMock->expects($this->once())->method('getAccessToken')->willReturn($this->fakeToken);


        $clientMock = $this->createMock(ClientInterface::class);
        $clientMock->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->stringContains('/fba/outbound/2020-07-01/fulfillmentOrders'),
                $this->callback(function ($options) {
                    // Check Header
                    if ($options['headers']['x-amz-access-token'] !== $this->fakeToken) return false;

                    // Check Payload content
                    $json = $options['json'];
                    return $json['sellerFulfillmentOrderId'] === $this->orderData['order_unique']
                        && $json['destinationAddress']['city'] === $this->orderData['shipping_city']
                        && $json['destinationAddress']['addressLine1'] === $this->orderData['shipping_street']
                        && $json['items'][0]['sellerSku'] === $this->orderData['products'][0]['product_code'];
                })
            )
            ->willReturn(new Response(200, [], '{}'));
        $service = new ShippingService($clientMock, $authMock);
        $result = $service->ship($orderMock, $buyerMock);

        $this->assertEquals($this->orderData['order_unique'], $result);
    }

    public function testCheckTrackingReturnsNullWhenPending(): void
    {
        $authMock = $this->createMock(AuthProviderInterface::class);
        $authMock->method('getAccessToken')->willReturn($this->fakeToken);

        // Mock Amazon Response where shipments array is empty (Not shipped yet)
        $amazonResponse = [
            'payload' => [
                'fulfillmentOrder' => [
                    'fulfillmentShipments' => [] 
                ]
            ]
        ];

        $clientMock = $this->createMock(ClientInterface::class);
        $clientMock->expects($this->once())
            ->method('request')
            ->willReturn(new Response(200, [], json_encode($amazonResponse)));

        $service = new ShippingService($clientMock, $authMock);
        $tracking = $service->checkTrackingStatus($this->orderData['order_unique']);

        $this->assertNull($tracking);
    }

    public function testShipThrowsRuntimeExceptionOnApiFailure(): void
    {
        $orderMock = $this->createMock(AbstractOrder::class);
        $orderMock->data = $this->orderData;
        $buyerMock = $this->createMock(BuyerInterface::class);

        $authMock = $this->createMock(AuthProviderInterface::class);
        $authMock->method('getAccessToken')->willReturn($this->fakeToken);

        $clientMock = $this->createMock(ClientInterface::class);
        // Simulate Guzzle throwing an exception (e.g. 400 Bad Request or 500 Server Error)
        $clientMock->expects($this->once())
            ->method('request')
            ->willThrowException(new RequestException('Error Communicating with Server', new Request('POST', 'test')));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Amazon FBA API Error');

        $service = new ShippingService($clientMock, $authMock);
        $service->ship($orderMock, $buyerMock);
    }

    public function testShipThrowsExceptionIfMissingRequiredAddressFields(): void
    {
        // Remove city from data
        unset($this->orderData['shipping_city']);

        $orderMock = $this->createMock(AbstractOrder::class);
        $orderMock->data = $this->orderData;
        $buyerMock = $this->createMock(BuyerInterface::class);

        $authMock = $this->createMock(AuthProviderInterface::class);
        $clientMock = $this->createMock(ClientInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing City');

        $service = new ShippingService($clientMock, $authMock);
        $service->ship($orderMock, $buyerMock);
    }

    public function testCheckTrackingThrowsExceptionOnApiFailure(): void
    {
        $authMock = $this->createMock(AuthProviderInterface::class);
        $authMock->method('getAccessToken')->willReturn($this->fakeToken);

        $clientMock = $this->createMock(ClientInterface::class);
        $clientMock->expects($this->once())
            ->method('request')
            ->willThrowException(new RequestException('Not Found', new Request('GET', 'test')));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Amazon FBA API Error');

        $service = new ShippingService($clientMock, $authMock);
        $service->checkTrackingStatus('ANY-ID');
    }
}