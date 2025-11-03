# WooCommerce Plugin - Quick Reference Guide

## 🚀 Quick Start

### API Endpoint
```
POST https://your-platform.com/api/tracking/events
Authorization: Bearer {token_prefix}.{secret}
Content-Type: application/json
```

### Minimal Event Payload
```json
{
  "event": "page_view",
  "identity": {
    "type": "cookie",
    "value": "abc123..."
  },
  "session_id": "uuid-v4",
  "url": "https://store.com/product",
  "timestamp": "2025-11-01T12:00:00Z",
  "idempotency_key": "uuid-v4"
}
```

---

## 📊 Event Types & Payloads

### 1. Page View
```json
{
  "event": "page_view",
  "properties": {
    "page_type": "product|cart|checkout|shop|category|home",
    "page_title": "Product Name"
  },
  "url": "...",
  "referrer": "..."
}
```

### 2. Product View
```json
{
  "event": "product_view",
  "properties": {
    "product_id": 456,
    "sku": "PROD-123",
    "name": "Product Name",
    "price": 29.99,
    "currency": "USD",
    "categories": ["Electronics"]
  }
}
```

### 3. Add to Cart
```json
{
  "event": "add_to_cart",
  "properties": {
    "product_id": 456,
    "quantity": 2,
    "price": 29.99,
    "total": 59.98
  },
  "revenue": 59.98,
  "currency": "USD"
}
```

### 4. Checkout Started
```json
{
  "event": "checkout_started",
  "properties": {
    "cart_total": 149.99,
    "item_count": 3
  },
  "revenue": 149.99,
  "currency": "USD"
}
```

### 5. Purchase (CONVERSION)
```json
{
  "event": "purchase",
  "properties": {
    "order_id": 789,
    "order_key": "wc_order_abc123",
    "total": 149.99,
    "subtotal": 120.00,
    "tax": 12.00,
    "shipping": 17.99,
    "currency": "USD",
    "payment_method": "stripe",
    "items": [
      {
        "product_id": 456,
        "sku": "PROD-123",
        "name": "Product Name",
        "quantity": 2,
        "price": 29.99,
        "total": 59.98
      }
    ],
    "coupons": ["SAVE10"],
    "discount": 10.00
  },
  "revenue": 149.99,
  "currency": "USD"
}
```

---

## 🔧 WooCommerce Hooks

### Product View
```php
add_action('woocommerce_before_single_product', function() {
    // Track product view
});
```

### Add to Cart
```php
add_action('woocommerce_add_to_cart', function($cart_item_key, $product_id) {
    // Track add to cart
}, 10, 2);
```

### Purchase/Order Complete
```php
add_action('woocommerce_thankyou', function($order_id) {
    // Track purchase (CRITICAL - server-side)
});

// OR more reliable:
add_action('woocommerce_payment_complete', function($order_id) {
    // Track purchase (server-side)
});
```

### Checkout
```php
add_action('woocommerce_checkout_process', function() {
    // Track checkout started
});
```

---

## 👤 Identity Types

| Type | Value | When to Use |
|------|-------|-------------|
| `cookie` | Anonymous cookie ID | Always (fallback) |
| `user_id` | WordPress user ID | When user is logged in |
| `email_hash` | SHA-256 of email | When email is available |
| `woocommerce_customer_id` | WC customer ID | When WC customer exists |

### Identity Payload
```json
{
  "identity": {
    "type": "user_id",
    "value": "123"
  },
  "customer_id": "wc_customer_id" // optional
}
```

---

## 🔗 Session Management

### Cookie Names
- `ts_customer_id` - Customer identity (365 days)
- `ts_session_id` - Session ID (30 minutes)

### Session Payload
```json
{
  "session_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

---

## 📊 UTM Parameters

### UTM Payload
```json
{
  "utm_source": "google",
  "utm_medium": "cpc",
  "utm_campaign": "summer_sale",
  "utm_term": "iphone",
  "utm_content": "ad_variant_1"
}
```

### PHP UTM Capture
```php
$utm_source = $_GET['utm_source'] ?? null;
$utm_medium = $_GET['utm_medium'] ?? null;
$utm_campaign = $_GET['utm_campaign'] ?? null;
$utm_term = $_GET['utm_term'] ?? null;
$utm_content = $_GET['utm_content'] ?? null;
```

### JavaScript UTM Capture
```javascript
const params = new URLSearchParams(window.location.search);
const utms = {
    source: params.get('utm_source'),
    medium: params.get('utm_medium'),
    campaign: params.get('utm_campaign'),
    term: params.get('utm_term'),
    content: params.get('utm_content'),
};
```

---

## 💰 Revenue Tracking

### Revenue Field
- Always send `revenue` as decimal number
- Always include `currency` (3-letter ISO code)
- For purchase events, `revenue` = order total

```json
{
  "revenue": 149.99,
  "currency": "USD"
}
```

---

## 🔐 API Authentication

### Token Format
```
Bearer {token_prefix}.{secret}
```

Example:
```
Bearer abc123def456.secret_key_here
```

### Headers
```php
$headers = [
    'Authorization' => 'Bearer ' . $token,
    'Content-Type' => 'application/json',
];
```

---

## ⚡ PHP API Client Example

```php
class Tracking_API_Client {
    private $endpoint;
    private $token;
    
    public function __construct($endpoint, $token) {
        $this->endpoint = $endpoint;
        $this->token = $token;
    }
    
    public function track_event($event_data) {
        $payload = array_merge([
            'idempotency_key' => $this->generate_uuid(),
            'timestamp' => gmdate('c'),
            'schema_version' => 1,
            'sdk_version' => '1.0.0',
        ], $event_data);
        
        $response = wp_remote_post($this->endpoint . '/events', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($payload),
            'timeout' => 5,
            'blocking' => false, // Async
        ]);
        
        if (is_wp_error($response)) {
            error_log('Tracking API Error: ' . $response->get_error_message());
        }
        
        return $response;
    }
    
    private function generate_uuid() {
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
}
```

---

## 🍪 Cookie Management

### Set Cookie
```php
setcookie('ts_customer_id', $customer_id, time() + (365 * 24 * 60 * 60), '/');
```

### Get Cookie
```php
$customer_id = $_COOKIE['ts_customer_id'] ?? null;
```

### JavaScript
```javascript
// Set
document.cookie = `ts_customer_id=${id}; max-age=${365 * 24 * 60 * 60}; path=/`;

// Get
function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
}
```

---

## 🎯 Priority Implementation Order

1. ✅ Purchase event (server-side) - MOST CRITICAL
2. ✅ Add to cart event
3. ✅ Checkout started event
4. ✅ Product view event
5. ✅ Page view event
6. Identity management
7. Session management
8. UTM tracking
9. Frontend JavaScript

---

## 🐛 Debugging

### Enable Debug Logging
```php
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('Tracking Event: ' . print_r($event_data, true));
    error_log('API Response: ' . print_r($response, true));
}
```

### Test Event
```php
$api_client = new Tracking_API_Client($endpoint, $token);
$api_client->track_event([
    'event' => 'test_event',
    'properties' => ['test' => true],
]);
```

---

## ⚠️ Important Notes

1. **Purchase events MUST be server-side** (can't be blocked)
2. **Always include idempotency_key** (prevents duplicates)
3. **Send timestamp in ISO 8601 format**
4. **Currency should be 3-letter ISO code** (USD, EUR, etc.)
5. **Revenue should be decimal** (not cents, unless specified)
6. **Use UUID v4 for session_id and idempotency_key**
7. **Respect cookie consent** (don't track before consent)
8. **Use async requests** (don't block page load)

---

## 📦 WooCommerce Data Extraction

### Order Data
```php
$order = wc_get_order($order_id);

$order_data = [
    'order_id' => $order->get_id(),
    'order_key' => $order->get_order_key(),
    'total' => $order->get_total(),
    'subtotal' => $order->get_subtotal(),
    'tax' => $order->get_total_tax(),
    'shipping' => $order->get_shipping_total(),
    'currency' => $order->get_currency(),
    'payment_method' => $order->get_payment_method(),
    'items' => [],
    'coupons' => $order->get_coupon_codes(),
    'discount' => $order->get_total_discount(),
];

foreach ($order->get_items() as $item) {
    $product = $item->get_product();
    $order_data['items'][] = [
        'product_id' => $product->get_id(),
        'sku' => $product->get_sku(),
        'name' => $item->get_name(),
        'quantity' => $item->get_quantity(),
        'price' => $item->get_subtotal() / $item->get_quantity(),
        'total' => $item->get_subtotal(),
    ];
}
```

### Product Data
```php
$product = wc_get_product($product_id);

$product_data = [
    'product_id' => $product->get_id(),
    'sku' => $product->get_sku(),
    'name' => $product->get_name(),
    'price' => $product->get_price(),
    'currency' => get_woocommerce_currency(),
    'categories' => wp_get_post_terms($product_id, 'product_cat', ['fields' => 'names']),
    'tags' => wp_get_post_terms($product_id, 'product_tag', ['fields' => 'names']),
];
```

---

This quick reference guide should help during plugin development!

