<?php

use App\Auth\EnvAuthProvider;
use App\Data\ApiBuyer;
use App\Data\ApiOrder;
use App\ShippingService;
use GuzzleHttp\Client;

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../'); // .env file in the project's root
$dotenv->safeLoad();

// Set up dependencies
$httpClient = new Client(['timeout' => 30.0]);
$authProvider = new EnvAuthProvider();
$service = new ShippingService($httpClient, $authProvider);

// Simple routing logic. in a real application, we should use a routing library.
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

header('Content-Type: application/json');

try {
    // ROUTE: POST /api/ship
    if ($method === 'POST' && $uri === '/api/ship') {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            throw new RuntimeException('Invalid JSON Body');
        }

        // Validate required fields
        if (!isset($input['order'])) {
            throw new RuntimeException('Missing order in request payload');
        }
        if (!isset($input['order']['order_id'])) {
            throw new RuntimeException('Missing order_id in request payload');
        } 
        if (!isset($input['buyer'])) {
            throw new RuntimeException('Missing buyer in request payload');
        }

        // Hydrate the objects
        $orderId = $input['order']['order_id'];

        $order = new ApiOrder($orderId, $input['order']);
        
        $buyer = new ApiBuyer($input['buyer']);

        $fulfillmentId = $service->ship($order, $buyer);

        echo json_encode([
            'status' => 'success',
            'data' => [
                'fulfillment_id' => $fulfillmentId,
                'message' => 'Order queued for fulfillment'
            ]
        ]);
        exit;
    }

    // ROUTE: GET /api/tracking
    if ($method === 'GET' && $uri === '/api/tracking') {
        $fulfillmentId = $_GET['id'] ?? null;

        if (!$fulfillmentId) {
            throw new RuntimeException('Missing "id" query parameter');
        }

        $tracking = $service->checkTrackingStatus($fulfillmentId);

        echo json_encode([
            'status' => 'success',
            'data' => [
                'fulfillment_id' => $fulfillmentId,
                'tracking_number' => $tracking,
                'state' => $tracking ? 'SHIPPED' : 'PROCESSING'
            ]
        ]);
        exit;
    }

    // 404 Handler
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Not Found']);

} catch (Throwable $e) {
    // Global Error Handler
    http_response_code($e instanceof RuntimeException ? 400 : 500);
    echo json_encode([
        'status' => 'error', 
        'message' => $e->getMessage()
    ]);
}