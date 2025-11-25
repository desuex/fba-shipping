<?php

namespace App;

use App\Data\AbstractOrder;
use App\Data\BuyerInterface;
use App\Auth\AuthProviderInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\ClientInterface;
use RuntimeException;

class ShippingService implements ShippingServiceInterface
{
    private const FULFILLMENT_API_ENDPOINT = '/fba/outbound/2020-07-01/fulfillmentOrders';


    public function __construct(
        private ClientInterface $httpClient,
        private AuthProviderInterface $authProvider,
        private string $apiBaseUrl = 'https://sellingpartnerapi-na.amazon.com',
    ) {

    }

    /**
     * Sends a command to Amazon's fulfillment network (FBA).
     * Returns the SellerFulfillmentOrderId on success.
     * * @throws RuntimeException
     */
    public function ship(AbstractOrder $order, BuyerInterface $buyer): string
    {
        $orderData = $this->prepareOrderData($order);
        $sellerFulfillmentOrderId = $orderData['order_unique'] ?? throw new RuntimeException('Order unique ID is required');
        $payload = $this->buildPayload($orderData, $buyer, $sellerFulfillmentOrderId);

        try {
            $this->sendFulfillmentOrder($payload);
            /**
             * In a task description it is mentioned that tracking number should be returned immediately,
             * but in practice, Amazon FBA does not provide a tracking number at the time of order creation.
             * Therefore, we return the SellerFulfillmentOrderId here.
             * But if the tracking number is required immediately, we could implement a polling mechanism here to wait for it.
             * while (true) {
             *    $trackingNumber = $this->retrieveTrackingNumber($sellerFulfillmentOrderId);
             *    sleep(5); // wait before retrying
             *    if ($trackingNumber) {
             *        return $trackingNumber;
             *    }
             * }
             * 
             * Or even better, we could use a queue system, Amazon webhooks, or another asynchronous mechanism to notify us when the tracking number is available.
             */
            
            return $sellerFulfillmentOrderId;
        } catch (GuzzleException $e) {
            throw new RuntimeException("Amazon FBA API Error: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Checks if a tracking number is available.
     * Returns the string tracking number if found, or NULL if still pending/processing.
     */
    public function checkTrackingStatus(string $sellerFulfillmentOrderId): ?string
    {
        try {
            return $this->retrieveTrackingNumber($sellerFulfillmentOrderId);
        } catch (GuzzleException $e) {
            throw new RuntimeException("Amazon FBA API Error: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    protected function prepareOrderData(AbstractOrder $order): array
    {
        if (empty($order->data)) {
            $order->load();
        }
        
        return $order->data;
    }

    protected function buildPayload(array $orderData, BuyerInterface $buyer, string $sellerFulfillmentOrderId): array
    {
        $orderDate = new \DateTimeImmutable($orderData['order_date'] ?? 'now');

        $destinationAddress = [
            'name'          => $orderData['buyer_name']       ?? $buyer->name,
            'addressLine1'  => $orderData['shipping_street']  ?? throw new RuntimeException('Missing Street'),
            'city'          => $orderData['shipping_city']    ?? throw new RuntimeException('Missing City'),
            'stateOrRegion' => $orderData['shipping_state']   ?? '', // Optional in some countries, required in others
            'postalCode'    => $orderData['shipping_zip']     ?? '',
            'countryCode'   => $orderData['shipping_country'] ?? throw new RuntimeException('Missing Country'),
        ];

        $items = array_map(fn($product) => [
            'sellerSku' => $product['product_code'], 
            'sellerFulfillmentOrderItemId' => (string)$product['order_product_id'], // It's not clear if SKU or product_code should be used here
            'quantity' => (int)($product['amount'] ?? $product['ammount'] ?? 1), // Handle typo fallback
        ], $orderData['products']);

        return [
            'sellerFulfillmentOrderId' => $sellerFulfillmentOrderId,
            'displayableOrderId'       => $sellerFulfillmentOrderId,
            'displayableOrderDate'     => $orderDate->format('Y-m-d\TH:i:s\Z'),
            'displayableOrderComment'  => $orderData['comments'] ?? 'Thank you for your order',
            'shippingSpeedCategory'    => 'Standard', // Could be parameterized based on order data, but not specified in the provided json
            'destinationAddress'       => $destinationAddress,
            'items'                    => $items,
        ];
    }

    private function getHeaders(): array
    {
        return [
            'x-amz-access-token' => $this->authProvider->getAccessToken(),
            'Content-Type' => 'application/json',
        ];
    }

    protected function sendFulfillmentOrder(array $payload): void
    {
        $this->httpClient->request(
            'POST',
            $this->apiBaseUrl . self::FULFILLMENT_API_ENDPOINT,
            [
                'json' => $payload,
                'headers' => $this->getHeaders()
            ] 
        );
    }

    protected function retrieveTrackingNumber(string $sellerFulfillmentOrderId): ?string
    {
        $response = $this->httpClient->request(
            'GET', 
            "{$this->apiBaseUrl}" . self::FULFILLMENT_API_ENDPOINT . "/{$sellerFulfillmentOrderId}",
            ['headers' => $this->getHeaders()]
        );

        $body = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        if (!empty($body['payload']['fulfillmentOrder']['fulfillmentShipments'])) {
            foreach ($body['payload']['fulfillmentOrder']['fulfillmentShipments'] as $shipment) {
                if (!empty($shipment['fulfillmentShipmentPackages'])) {
                    foreach ($shipment['fulfillmentShipmentPackages'] as $package) {
                        if (!empty($package['trackingNumber'])) {
                            return $package['trackingNumber'];
                        }
                    }
                }
            }
        }

        return null;
    }
}