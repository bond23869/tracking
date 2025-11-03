# WooCommerce Tracking Plugin - Implementation Plan

## Overview
A WordPress plugin that integrates with WooCommerce to send tracking events to your tracking SaaS platform. The plugin will capture customer behavior, e-commerce events, and marketing attribution data.

---

## 📋 Plugin Structure

### 1. Plugin Basics
- **Name**: `tracking-saas-woocommerce` (or your brand name)
- **Version**: 1.0.0
- **Minimum Requirements**: WordPress 5.8+, WooCommerce 6.0+, PHP 7.4+
- **Main Plugin File**: `tracking-saas-woocommerce.php`

### 2. Directory Structure
```
tracking-saas-woocommerce/
├── tracking-saas-woocommerce.php    # Main plugin file
├── includes/
│   ├── class-tracker.php             # Core tracking class
│   ├── class-api-client.php          # API client for ingestion endpoint
│   ├── class-identity-manager.php    # Customer identity management
│   ├── class-session-manager.php     # Session handling
│   ├── class-utm-tracker.php         # UTM parameter tracking
│   ├── class-event-tracker.php       # Event tracking logic
│   └── class-privacy-handler.php     # GDPR/privacy compliance
├── admin/
│   ├── class-admin-settings.php      # Admin settings page
│   ├── class-admin-ajax.php           # Admin AJAX handlers
│   └── views/
│       └── settings-page.php         # Settings UI
├── assets/
│   ├── js/
│   │   ├── frontend-tracker.js       # Frontend JavaScript
│   │   └── admin-settings.js         # Admin JavaScript
│   └── css/
│       └── admin.css                  # Admin styles
├── languages/                         # Translation files
└── README.md                          # Plugin documentation
```

---

## 🔧 Core Functionality

### 3. Configuration & Settings

#### 3.1 Admin Settings Page
**Location**: WooCommerce → Settings → Tracking (new tab)

**Settings Fields:**
- **API Endpoint URL**: `https://your-platform.com/api/tracking`
- **Ingestion Token**: Store token securely (encrypted)
- **Enable/Disable Tracking**: Toggle switch
- **Track Logged-in Users**: Checkbox
- **Respect Do Not Track**: Checkbox
- **GDPR Consent Mode**: Dropdown (none/implied/explicit)
- **Debug Mode**: Checkbox (log API calls)
- **Advanced Settings**:
  - Custom event names mapping
  - Exclude certain user roles
  - Custom cookie expiration
  - Server-side tracking toggle

#### 3.2 Settings Storage
```php
// Store in wp_options with prefix
update_option('tracking_saas_api_endpoint', $endpoint);
update_option('tracking_saas_token', $encrypted_token);
update_option('tracking_saas_enabled', true);
```

---

## 🎯 Event Tracking

### 4. Events to Track

#### 4.1 Page View Events
**Hook**: `wp_head`, `wp_footer`
- Track all page views
- Include page type (product, cart, checkout, etc.)
- Capture current URL, referrer
- Send UTM parameters if present

**Event Structure:**
```json
{
  "event": "page_view",
  "properties": {
    "page_type": "product|cart|checkout|shop|category|home",
    "page_title": "...",
    "post_id": 123,
    "product_id": 456  // if product page
  },
  "url": "...",
  "referrer": "..."
}
```

#### 4.2 Product View Events
**Hook**: `woocommerce_before_single_product`, `woocommerce_after_single_product`
- Track product detail page views
- Include product data (ID, SKU, name, price, categories)

**Event Structure:**
```json
{
  "event": "product_view",
  "properties": {
    "product_id": 456,
    "sku": "PROD-123",
    "name": "Product Name",
    "price": 29.99,
    "currency": "USD",
    "categories": ["Electronics", "Phones"],
    "tags": ["featured", "new"]
  }
}
```

#### 4.3 Add to Cart Events
**Hook**: `woocommerce_add_to_cart`
- Track when products are added to cart
- Include quantity, price, product details

**Event Structure:**
```json
{
  "event": "add_to_cart",
  "properties": {
    "product_id": 456,
    "quantity": 2,
    "price": 29.99,
    "total": 59.98,
    "currency": "USD"
  },
  "revenue": 59.98,
  "currency": "USD"
}
```

#### 4.4 Remove from Cart Events
**Hook**: `woocommerce_cart_item_removed`
- Track cart removals

#### 4.5 Cart Update Events
**Hook**: `woocommerce_cart_updated`
- Track cart quantity changes

#### 4.6 Checkout Started Events
**Hook**: `woocommerce_checkout_process`
- Track when checkout process begins
- Include cart total, item count

**Event Structure:**
```json
{
  "event": "checkout_started",
  "properties": {
    "cart_total": 149.99,
    "item_count": 3,
    "currency": "USD"
  },
  "revenue": 149.99,
  "currency": "USD"
}
```

#### 4.7 Purchase/Order Events (CRITICAL)
**Hook**: `woocommerce_thankyou`, `woocommerce_payment_complete`
- Track completed orders
- Include full order data
- **This is a conversion event**

**Event Structure:**
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

#### 4.8 Search Events
**Hook**: `pre_get_posts` (with WooCommerce search filter)
- Track product searches

**Event Structure:**
```json
{
  "event": "product_search",
  "properties": {
    "search_query": "iphone 15",
    "results_count": 5
  }
}
```

#### 4.9 Category/Shop View Events
**Hook**: `woocommerce_before_shop_loop`
- Track category page views

#### 4.10 Wishlist Events (if plugin exists)
**Hook**: WooCommerce Wishlist plugin hooks
- Track wishlist additions/removals

---

## 👤 Identity & Customer Management

### 5. Identity Resolution Strategy

#### 5.1 Anonymous Visitors
- **Cookie-based identity**: Generate unique cookie ID (`tracking_cid`)
- Cookie name: `ts_customer_id`
- Expiration: 365 days (configurable)
- First-party cookie (same domain)

#### 5.2 Logged-in Users
- **WordPress User ID**: Use `get_current_user_id()`
- **Email hash**: Hash user email (SHA-256)
- Link anonymous cookie ID to user ID on login

#### 5.3 Identity Merge Logic
```php
// On user login
if (isset($_COOKIE['ts_customer_id'])) {
    $cookie_customer_id = $_COOKIE['ts_customer_id'];
    // Link cookie identity to user_id identity
    // Send identity_merge event
}
```

#### 5.4 Identity Types to Send
- `cookie`: Anonymous cookie ID
- `user_id`: WordPress user ID (when logged in)
- `email_hash`: Hashed email address (when available)
- `woocommerce_customer_id`: WooCommerce customer ID

---

## 🔗 Session Management

### 6. Session Tracking

#### 6.1 Session ID Generation
- Generate UUID v4 session ID
- Store in cookie: `ts_session_id`
- Expiration: 30 minutes (matches backend)
- Renew on activity

#### 6.2 Session Lifecycle
```php
// Check if session exists
$session_id = $_COOKIE['ts_session_id'] ?? null;

// If no session or expired (30 min), create new
if (!$session_id || session_expired($session_id)) {
    $session_id = generate_uuid();
    setcookie('ts_session_id', $session_id, time() + 1800, '/');
}

// Send session_id with every event
```

#### 6.3 Session Continuity
- Continue same session across page views within 30 minutes
- Break session on:
  - 30 minutes of inactivity
  - New campaign (different UTMs)
  - Referrer change (from external to internal or vice versa)

---

## 📊 UTM & Marketing Attribution

### 7. UTM Parameter Capture

#### 7.1 URL Parameter Parsing
```php
// Capture from $_GET
$utm_source = $_GET['utm_source'] ?? null;
$utm_medium = $_GET['utm_medium'] ?? null;
$utm_campaign = $_GET['utm_campaign'] ?? null;
$utm_term = $_GET['utm_term'] ?? null;
$utm_content = $_GET['utm_content'] ?? null;
```

#### 7.2 UTM Storage Strategy
- **First Touch**: Store UTMs in cookie on first visit
  - Cookie: `ts_first_touch` (JSON, 365 days)
- **Last Touch**: Update UTMs on each visit with new campaign
  - Cookie: `ts_last_touch` (JSON, 30 days)
- **Current Session**: Include UTMs from URL in every event

#### 7.3 UTM Link Decoration
- Automatically append UTM parameters to internal links if present
- Preserve UTMs through checkout flow

#### 7.4 Referrer Tracking
- Capture `document.referrer` (JavaScript)
- Or `$_SERVER['HTTP_REFERER']` (server-side)
- Parse domain for categorization

---

## 🌐 API Integration

### 8. API Client Implementation

#### 8.1 API Client Class
```php
class Tracking_API_Client {
    private $endpoint;
    private $token;
    
    public function track_event($event_data) {
        // Build payload
        // Add identity, session, UTMs
        // Send POST request
        // Handle errors/retries
    }
    
    private function send_request($data) {
        $response = wp_remote_post($this->endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data),
            'timeout' => 5,
            'blocking' => false, // Async for performance
        ]);
        
        return $response;
    }
}
```

#### 8.2 Request Payload Structure
```php
$payload = [
    'event' => $event_name,
    'properties' => $properties,
    'identity' => [
        'type' => 'cookie|user_id|email_hash',
        'value' => $identity_value,
    ],
    'session_id' => $session_id,
    'customer_id' => $woocommerce_customer_id, // if available
    'url' => $current_url,
    'referrer' => $referrer,
    'utm_source' => $utm_source,
    'utm_medium' => $utm_medium,
    'utm_campaign' => $utm_campaign,
    'utm_term' => $utm_term,
    'utm_content' => $utm_content,
    'revenue' => $revenue,
    'currency' => get_woocommerce_currency(),
    'timestamp' => gmdate('c'),
    'idempotency_key' => generate_uuid(),
    'schema_version' => 1,
    'sdk_version' => '1.0.0',
];
```

#### 8.3 Error Handling & Retries
- Log failed requests to option/transient
- Retry queue for failed requests (background cron)
- Exponential backoff for retries
- Dead letter queue after max retries

---

## 🖥️ Frontend JavaScript

### 9. Client-Side Tracking

#### 9.1 JavaScript Tracker
```javascript
// assets/js/frontend-tracker.js
(function() {
    'use strict';
    
    window.TrackingSaaS = {
        // Initialize
        init: function() {
            this.identity.init();
            this.session.init();
            this.utm.init();
            this.trackPageView();
        },
        
        // Identity management
        identity: {
            get: function() {
                return this.getCookie('ts_customer_id') || this.create();
            },
            create: function() {
                const id = this.generateUUID();
                this.setCookie('ts_customer_id', id, 365);
                return id;
            },
            // ... cookie helpers
        },
        
        // UTM tracking
        utm: {
            capture: function() {
                const params = new URLSearchParams(window.location.search);
                return {
                    source: params.get('utm_source'),
                    medium: params.get('utm_medium'),
                    campaign: params.get('utm_campaign'),
                    term: params.get('utm_term'),
                    content: params.get('utm_content'),
                };
            },
            preserve: function() {
                // Preserve UTMs in localStorage
                const utms = this.capture();
                if (utms.source) {
                    localStorage.setItem('ts_utms', JSON.stringify(utms));
                }
            },
        },
        
        // Event tracking
        track: function(eventName, properties) {
            // Send to WordPress AJAX endpoint
            fetch('/wp-admin/admin-ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'tracking_saas_track_event',
                    event: eventName,
                    properties: properties,
                }),
            });
        },
    };
    
    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            window.TrackingSaaS.init();
        });
    } else {
        window.TrackingSaaS.init();
    }
})();
```

#### 9.2 WooCommerce-Specific Events (JavaScript)
```javascript
// Hook into WooCommerce events
jQuery(document).on('added_to_cart', function(event, fragments, cart_hash, button) {
    const productId = jQuery(button).data('product_id');
    TrackingSaaS.track('add_to_cart', {
        product_id: productId,
    });
});
```

---

## 🔒 Privacy & GDPR Compliance

### 10. Privacy Features

#### 10.1 Cookie Consent Integration
- Integrate with popular consent plugins:
  - Cookie Notice
  - GDPR Cookie Consent
  - Complianz
- Only track after consent given

#### 10.2 Do Not Track (DNT) Support
```php
$dnt_header = $_SERVER['HTTP_DNT'] ?? '0';
if ($dnt_header === '1' && get_option('tracking_saas_respect_dnt')) {
    // Skip tracking
    return;
}
```

#### 10.3 User Data Deletion
- Hook into `wp_delete_user`
- Send deletion request to API
- Clear local cookies/identities

#### 10.4 Data Export
- Provide user data export (GDPR right to access)
- Export all events for a user

---

## ⚙️ Server-Side Tracking

### 11. PHP Event Tracking

#### 11.1 Why Server-Side?
- More reliable (no ad blockers)
- Better for conversions (purchases)
- Can't be blocked

#### 11.2 When to Use Server-Side
- **Always**: Purchase events (most important)
- **Always**: Checkout started
- **Optional**: All events (configurable)

#### 11.3 Server-Side Implementation
```php
// Hook into WooCommerce events
add_action('woocommerce_thankyou', 'tracking_saas_track_purchase', 10, 1);
function tracking_saas_track_purchase($order_id) {
    $order = wc_get_order($order_id);
    
    $event_data = [
        'event' => 'purchase',
        'properties' => [
            'order_id' => $order_id,
            'order_key' => $order->get_order_key(),
            'total' => $order->get_total(),
            'currency' => $order->get_currency(),
            // ... more order data
        ],
        'revenue' => $order->get_total(),
        'currency' => $order->get_currency(),
    ];
    
    $api_client = new Tracking_API_Client();
    $api_client->track_event($event_data);
}
```

---

## 🧪 Testing & Debugging

### 12. Development Features

#### 12.1 Debug Mode
- Log all API calls to `wp-content/debug.log`
- Include request/response data
- Show errors in admin notice

#### 12.2 Test Events
- Admin button to send test event
- Verify API connection
- Check token validity

#### 12.3 Health Check
```php
// Test API endpoint
$response = wp_remote_get($endpoint . '/health');
if (wp_remote_retrieve_response_code($response) === 200) {
    // API is healthy
}
```

---

## 📦 Installation & Setup

### 13. Installation Flow

#### 13.1 Initial Setup Wizard
1. Plugin activation
2. Show setup wizard:
   - API endpoint URL
   - Ingestion token input
   - Test connection button
   - Enable tracking toggle

#### 13.2 Activation Hook
```php
register_activation_hook(__FILE__, 'tracking_saas_activate');
function tracking_saas_activate() {
    // Set default options
    // Create required database tables (if needed)
    // Schedule cron jobs
}
```

---

## 🚀 Performance Optimization

### 14. Optimization Strategies

#### 14.1 Async Requests
- Use `wp_remote_post` with `blocking => false`
- Queue events for batch processing
- Background cron job for retries

#### 14.2 Batching
- Collect events in JavaScript
- Send in batches (every 5 seconds or on page unload)
- Server-side: batch API calls

#### 14.3 Caching
- Cache identity/session in transients
- Avoid duplicate API calls

#### 14.4 Lazy Loading
- Load tracking script asynchronously
- Defer non-critical tracking

---

## 📝 Implementation Checklist

### Phase 1: Core Setup (Week 1)
- [ ] Create plugin structure
- [ ] Admin settings page
- [ ] API client class
- [ ] Token storage (encrypted)
- [ ] Basic page view tracking

### Phase 2: WooCommerce Integration (Week 2)
- [ ] Product view tracking
- [ ] Add to cart tracking
- [ ] Checkout tracking
- [ ] Purchase tracking (server-side)
- [ ] Order data extraction

### Phase 3: Identity & Sessions (Week 2)
- [ ] Cookie-based identity
- [ ] User ID linking
- [ ] Session management
- [ ] Identity merge logic

### Phase 4: UTM & Attribution (Week 3)
- [ ] UTM parameter capture
- [ ] First/last touch storage
- [ ] Referrer tracking
- [ ] UTM preservation

### Phase 5: Frontend JavaScript (Week 3)
- [ ] JavaScript tracker
- [ ] Event listeners
- [ ] WooCommerce JS hooks
- [ ] Async event sending

### Phase 6: Privacy & Polish (Week 4)
- [ ] GDPR compliance
- [ ] Cookie consent integration
- [ ] DNT support
- [ ] Error handling
- [ ] Debug mode
- [ ] Documentation

---

## 🎯 Key Implementation Details

### 15. Priority Events (MVP)
1. **Purchase** - CRITICAL (conversion)
2. **Add to Cart** - High value
3. **Checkout Started** - Funnel tracking
4. **Product View** - Engagement
5. **Page View** - Foundation

### 16. Event Naming Convention
- Use snake_case: `product_view`, `add_to_cart`, `purchase`
- Be consistent
- Document all events

### 17. WooCommerce Hooks Reference
```php
// Product view
woocommerce_before_single_product
woocommerce_after_single_product_summary

// Add to cart
woocommerce_add_to_cart
woocommerce_add_to_cart_fragments

// Cart
woocommerce_cart_item_removed
woocommerce_cart_updated

// Checkout
woocommerce_checkout_process
woocommerce_checkout_order_processed

// Order complete
woocommerce_thankyou
woocommerce_payment_complete
woocommerce_order_status_completed
```

---

## 📚 Documentation Needs

### 18. User Documentation
- Installation guide
- Configuration guide
- Event reference
- Troubleshooting
- FAQ

### 19. Developer Documentation
- Hooks and filters
- Extending the plugin
- Custom events
- API reference

---

## 🔐 Security Considerations

### 20. Security Best Practices
- Encrypt token storage
- Sanitize all input
- Validate API responses
- Use nonces for AJAX
- Rate limiting
- Input validation

---

## ✅ Success Metrics

### 21. How to Measure Success
- Event delivery rate
- API response times
- Error rates
- User adoption
- Data quality (completeness)

---

## 🚨 Common Pitfalls to Avoid

1. **Ad Blockers**: Use server-side for critical events
2. **Cookie Consent**: Don't track before consent
3. **Performance**: Don't block page loads
4. **Duplicate Events**: Use idempotency keys
5. **Token Security**: Never log tokens
6. **Test Environment**: Don't send test data to production API

---

This plan provides a complete roadmap for building a production-ready WooCommerce tracking plugin. Start with Phase 1 and iterate!

