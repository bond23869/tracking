# API Testing Guide

## Setup

The API is ready to use! You just need to:

1. **Configure your Herd site** (if not already done):
   - The site should point to this directory (`/Users/JanKotnik/Herd/tracking`)
   - Make sure your site URL matches what's in your `.env` file

2. **Run migrations** (if not already done):
   ```bash
   php artisan migrate
   ```

3. **Create an ingestion token**:
   ```bash
   php artisan tracking:create-token --create-test-data
   ```
   
   This will:
   - Create a test organization, account, and website if needed
   - Generate an ingestion token
   - Display the full token (save it - it cannot be retrieved later!)

## Testing the API

### Method 1: Using the test script

```bash
php test-api.php <token> [event-type]
```

Examples:
```bash
# Test page_view event
php test-api.php ySLia7Fbh7v1.9DfbxNvG62iaZLhj5OPbW8a75eZB4Ff4 page_view

# Test purchase event
php test-api.php ySLia7Fbh7v1.9DfbxNvG62iaZLhj5OPbW8a75eZB4Ff4 purchase

# Test add_to_cart event
php test-api.php ySLia7Fbh7v1.9DfbxNvG62iaZLhj5OPbW8a75eZB4Ff4 add_to_cart
```

### Method 2: Using curl

```bash
curl -X POST http://localhost/api/tracking/events \
  -H "Authorization: Bearer <your-token>" \
  -H "Content-Type: application/json" \
  -d '{
    "event": "page_view",
    "identity": {
      "type": "cookie",
      "value": "test-cookie-123"
    },
    "url": "https://example.com",
    "idempotency_key": "unique-uuid-here"
  }'
```

### Method 3: Health check (no auth required)

```bash
curl http://localhost/api/tracking/health
```

## API Endpoints

### POST `/api/tracking/events`

**Authentication**: Required (Bearer token in Authorization header)

**Request Format**:
```json
{
  "event": "page_view",
  "properties": {},
  "identity": {
    "type": "cookie",
    "value": "cookie-value"
  },
  "session_id": "uuid-v4",
  "url": "https://example.com",
  "referrer": "https://google.com",
  "utm_source": "google",
  "utm_medium": "cpc",
  "utm_campaign": "summer-sale",
  "revenue": 99.99,
  "currency": "USD",
  "idempotency_key": "uuid-v4",
  "timestamp": "2025-11-03T07:00:00Z"
}
```

**Success Response** (201):
```json
{
  "success": true,
  "event_id": 1,
  "customer_id": 1,
  "session_id": 1
}
```

### GET `/api/tracking/health`

**Authentication**: Not required

**Response**:
```json
{
  "status": "ok",
  "timestamp": "2025-11-03T07:00:00Z"
}
```

## Event Types

The API supports various event types:

- `page_view` - Page views
- `product_view` - Product page views
- `add_to_cart` - Items added to cart
- `checkout_started` - Checkout process started
- `purchase` - Purchase/order completed (conversion event)

## Token Management

### Creating a new token

```bash
php artisan tracking:create-token --create-test-data
```

Or for an existing website:
```bash
php artisan tracking:create-token --website-id=1 --name="My Token"
```

### Token Format

Tokens are in the format: `{prefix}.{secret}`

Example: `abc123def456.secret_key_here`

The prefix is 12 characters, and the full token is stored as a hash in the database.

## Troubleshooting

### 404 Not Found

If you get a 404 error, make sure:
1. Your Herd site is configured correctly
2. Your `APP_URL` in `.env` matches your site URL
3. The site is running (`php artisan serve` or via Herd)

### 401 Unauthorized

- Check that your token is correct
- Make sure you're using `Bearer <token>` format
- Verify the token hasn't been revoked or expired

### 500 Internal Server Error

- Check Laravel logs: `storage/logs/laravel.log`
- Make sure all migrations have run: `php artisan migrate`
- Verify database connection settings in `.env`

## Examples

See `WOOCOMMERCE_PLUGIN_QUICK_REFERENCE.md` for detailed examples of event payloads for WooCommerce integration.

