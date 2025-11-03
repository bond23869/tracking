#!/usr/bin/env php
<?php

/**
 * Test script for sending tracking API requests
 * 
 * Usage:
 *   php test-api.php <token> [event-type]
 * 
 * Examples:
 *   php test-api.php abc123.secret_key_here
 *   php test-api.php abc123.secret_key_here page_view
 *   php test-api.php abc123.secret_key_here purchase
 */

if ($argc < 2) {
    echo "Usage: php test-api.php <token> [event-type]\n";
    echo "\n";
    echo "Examples:\n";
    echo "  php test-api.php abc123.secret_key_here\n";
    echo "  php test-api.php abc123.secret_key_here page_view\n";
    echo "  php test-api.php abc123.secret_key_here purchase\n";
    exit(1);
}

$token = $argv[1];
$eventType = $argv[2] ?? 'page_view';

// Get base URL from environment or use default
$baseUrl = getenv('APP_URL') ?: 'http://localhost';

// Generate UUID for idempotency
function generateUuid(): string {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
}

// Build event payload based on type
$eventData = [
    'event' => $eventType,
    'idempotency_key' => generateUuid(),
    'timestamp' => date('c'),
    'schema_version' => 1,
    'sdk_version' => '1.0.0',
    'identity' => [
        'type' => 'cookie',
        'value' => 'test-cookie-' . bin2hex(random_bytes(8)),
    ],
    'session_id' => generateUuid(),
    'url' => 'https://example.com/test',
    'referrer' => 'https://google.com',
    'utm_source' => 'test',
    'utm_medium' => 'test',
    'utm_campaign' => 'test-campaign',
];

switch ($eventType) {
    case 'page_view':
        $eventData['properties'] = [
            'page_type' => 'product',
            'page_title' => 'Test Product Page',
        ];
        break;
    
    case 'product_view':
        $eventData['properties'] = [
            'product_id' => 123,
            'sku' => 'PROD-123',
            'name' => 'Test Product',
            'price' => 29.99,
            'currency' => 'USD',
            'categories' => ['Electronics'],
        ];
        break;
    
    case 'add_to_cart':
        $eventData['properties'] = [
            'product_id' => 123,
            'quantity' => 2,
            'price' => 29.99,
            'total' => 59.98,
        ];
        $eventData['revenue'] = 59.98;
        $eventData['currency'] = 'USD';
        break;
    
    case 'checkout_started':
        $eventData['properties'] = [
            'cart_total' => 149.99,
            'item_count' => 3,
        ];
        $eventData['revenue'] = 149.99;
        $eventData['currency'] = 'USD';
        break;
    
    case 'purchase':
        $eventData['properties'] = [
            'order_id' => 789,
            'order_key' => 'wc_order_' . bin2hex(random_bytes(8)),
            'total' => 149.99,
            'subtotal' => 120.00,
            'tax' => 12.00,
            'shipping' => 17.99,
            'currency' => 'USD',
            'payment_method' => 'stripe',
            'items' => [
                [
                    'product_id' => 123,
                    'sku' => 'PROD-123',
                    'name' => 'Test Product',
                    'quantity' => 2,
                    'price' => 29.99,
                    'total' => 59.98,
                ],
            ],
            'coupons' => ['SAVE10'],
            'discount' => 10.00,
        ];
        $eventData['revenue'] = 149.99;
        $eventData['currency'] = 'USD';
        break;
    
    default:
        $eventData['properties'] = ['test' => true];
        break;
}

// Prepare request
$url = rtrim($baseUrl, '/') . '/api/tracking/events';
$headers = [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => json_encode($eventData),
    CURLOPT_VERBOSE => false,
]);

echo "Sending request to: {$url}\n";
echo "Event type: {$eventType}\n";
echo "Payload:\n";
echo json_encode($eventData, JSON_PRETTY_PRINT) . "\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "ERROR: {$error}\n";
    exit(1);
}

echo "Response (HTTP {$httpCode}):\n";
$decoded = json_decode($response, true);
if ($decoded) {
    echo json_encode($decoded, JSON_PRETTY_PRINT) . "\n";
} else {
    echo $response . "\n";
}

if ($httpCode >= 200 && $httpCode < 300) {
    echo "\n✅ Request successful!\n";
    exit(0);
} else {
    echo "\n❌ Request failed!\n";
    exit(1);
}

