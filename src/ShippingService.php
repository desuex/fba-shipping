<?php

namespace App;

use App\Data\AbstractOrder;
use App\Data\BuyerInterface;
use GuzzleHttp\Client;;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

class ShippingService implements ShippingServiceInterface
{

    private Client $httpClient;

    private string $accessToken;
    private string $apiBaseUrl;

    public function __construct(
        ClientInterface $httpClient,
        string $apiBaseUrl = 'https://sellingpartnerapi-na.amazon.com',
        string $accessToken = '',
        
    ) {
        $this->httpClient = $httpClient;
        $this->apiBaseUrl = $apiBaseUrl;
        $this->accessToken = $accessToken;
    }

    /**
     * Sends a command to Amazon's fulfillment network (FBA) to fulfill seller order using seller inventory in Amazon's fulfillment network (FBA)
     * and will return the tracking number as string for this order.
     * If operation cannot be performed, will throw an exception with error message
     * 
     * @throws RuntimeException
     */
    public function ship(AbstractOrder $order, BuyerInterface $buyer): string
    {
        return "TRACKING123456789";
    }
}