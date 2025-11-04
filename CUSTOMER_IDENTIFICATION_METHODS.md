# Customer Identification Methods

Based on the schema, here are all the customer identification methods we can use to track and link customers across sessions, devices, and browsers.

## Currently Implemented Methods

### 1. **Cookie Identity** (`cookie`)
- **Type**: `cookie` identity type
- **Confidence**: 1.0 (high)
- **Source**: SDK
- **Use Case**: Primary anonymous tracking method
- **Persistence**: Survives browser restarts (until cleared)
- **Limitation**: Lost when cookies are cleared, incognito mode

### 2. **User ID** (`user_id`)
- **Type**: `user_id` identity type
- **Confidence**: 1.0 (high)
- **Source**: login
- **Use Case**: Authenticated users (WordPress/WooCommerce user ID)
- **Persistence**: Permanent across devices (when logged in)
- **Limitation**: Only available when user is logged in

### 3. **Email Hash** (`email_hash`)
- **Type**: `email_hash` identity type
- **Confidence**: 0.95 (very high)
- **Source**: login, checkout, account creation
- **Use Case**: Cross-device tracking, linking anonymous to authenticated
- **Persistence**: Permanent
- **Note**: Also stored directly in `customers.email_hash` field

### 4. **Google Analytics Client ID** (`ga_cid`)
- **Type**: `ga_cid` identity type
- **Confidence**: 0.9 (high)
- **Source**: Google Analytics integration
- **Use Case**: Cross-platform tracking with GA
- **Persistence**: Browser-local storage

### 5. **IP + User-Agent Fingerprint** (`fingerprint`) ❌ **NOT RECOMMENDED**
- **Type**: `fingerprint` identity type
- **Confidence**: 0.5 (low-medium) - **TOO LOW FOR RELIABLE USE**
- **Source**: heuristic
- **Status**: **DEPRECATED** - Not used due to reliability issues
- **Why Not Used?**:
  - IP addresses are shared by many users (NAT, office networks, public WiFi)
  - User-Agent strings are not unique (many users have same browser/version)
  - High false positive rate (different users can have same IP + User-Agent)
  - IP addresses change frequently (dynamic IPs, mobile networks, VPNs)
  - **Result**: Without identity data, we return `null` (no customer created) instead of creating unreliable fingerprint-based customers
- **Alternative**: Use proper identity types (cookie, user_id, email_hash) instead

### 6. **IP-Based Cookie Linking** (heuristic)
- **Type**: Heuristic matching
- **Confidence**: 0.7 (medium-high)
- **Source**: heuristic
- **Use Case**: Linking new cookies to existing customers when cookies cleared
- **Time Window**: 2 hours
- **Condition**: No recent cookie identity (within 30 minutes)

---

## Additional Methods We Can Implement

### 7. **Email Hash Cross-Matching** (high priority)
- **How**: When `email_hash` identity is found, find customer by `customers.email_hash` field
- **Confidence**: 0.95 (very high)
- **Source**: login, checkout
- **Use Case**: Link all identities when email appears
- **Implementation**: Check `customers.email_hash` when email_hash identity is provided

### 8. **Phone Number Hash** (`phone_hash`)
- **Type**: `phone_hash` identity type
- **Confidence**: 0.85 (high)
- **Source**: checkout, account creation
- **Use Case**: Alternative identifier for mobile users
- **Privacy**: Hash phone numbers (SHA-256)

### 9. **Local Storage ID** (`local_storage_id`)
- **Type**: `local_storage_id` identity type
- **Confidence**: 0.8 (high)
- **Source**: SDK
- **Use Case**: Persistent identifier across sessions
- **Persistence**: Survives browser restarts (until cleared)

### 10. **Session Storage ID** (`session_storage_id`)
- **Type**: `session_storage_id` identity type
- **Confidence**: 0.75 (medium-high)
- **Source**: SDK
- **Use Case**: Tab-scoped identifier
- **Persistence**: Survives page reloads, cleared on tab close

### 11. **Device Fingerprint** (`device_fingerprint`)
- **Type**: `device_fingerprint` identity type
- **Confidence**: 0.7 (medium)
- **Source**: SDK
- **Components**: Canvas fingerprint, fonts, screen resolution, timezone, language
- **Use Case**: Cross-browser tracking on same device
- **Privacy**: Considered fingerprinting, may need consent

### 12. **Browser Fingerprint** (`browser_fingerprint`)
- **Type**: `browser_fingerprint` identity type
- **Confidence**: 0.7 (medium)
- **Source**: SDK
- **Components**: User-Agent + screen resolution + timezone + language + plugins
- **Use Case**: Device identification without cookies

### 13. **Payment Method Fingerprint** (`payment_fingerprint`)
- **Type**: `payment_fingerprint` identity type
- **Confidence**: 0.85 (high)
- **Source**: checkout events
- **Components**: Hash of last 4 digits + billing address/postal code
- **Use Case**: Link checkout to existing customer
- **Privacy**: Only hash, not full card number

### 14. **Shipping Address Hash** (`shipping_address_hash`)
- **Type**: `shipping_address_hash` identity type
- **Confidence**: 0.8 (high)
- **Source**: checkout events
- **Components**: Hash of shipping address components
- **Use Case**: Link orders to customers

### 15. **Social Media IDs**
- **Types**: `facebook_id`, `google_id`, `apple_id`, `twitter_id`
- **Confidence**: 0.95 (very high)
- **Source**: OAuth login
- **Use Case**: Cross-platform authentication
- **Persistence**: Permanent

### 16. **Order/Transaction ID** (`order_id`, `transaction_id`)
- **Type**: `order_id` or `transaction_id` identity type
- **Confidence**: 0.9 (high, short window)
- **Source**: checkout events
- **Use Case**: Temporary correlation during checkout flow
- **Time Window**: Short (minutes to hours)
- **Note**: Can link anonymous sessions to conversions

### 17. **Referrer + IP + Time Window** (heuristic)
- **Type**: Heuristic matching
- **Confidence**: 0.6 (medium)
- **Source**: heuristic
- **Components**: Referrer domain + IP + narrow time window (< 5 minutes)
- **Use Case**: Link sessions from same source quickly
- **Limitation**: Low accuracy, many false positives

### 18. **Cross-Device Email Linking** (heuristic)
- **Type**: Heuristic matching
- **Confidence**: 0.95 (very high)
- **Source**: heuristic
- **How**: When email_hash appears, link ALL identities to same customer
- **Use Case**: Merge anonymous sessions when user logs in
- **Implementation**: Periodic job to merge customers by email_hash

---

## Identity Resolution Priority

When resolving a customer, we should check in this order:

1. **Direct `customer_id`** (if provided) - Confidence: 1.0
2. **Identity-based lookup** (cookie, user_id, email_hash, etc.) - Confidence: 1.0
3. **Email hash cross-match** (check `customers.email_hash`) - Confidence: 0.95
4. **IP-based cookie linking** (for new cookies) - Confidence: 0.7
5. **Fingerprint fallback** (IP + User-Agent) - Confidence: 0.5

---

## Confidence Scores

Confidence scores help track the reliability of customer identification:

- **1.0**: Direct identity match (cookie, user_id)
- **0.95**: High confidence (email_hash, social IDs)
- **0.85-0.9**: High confidence (phone, payment, order ID)
- **0.7-0.8**: Medium-high confidence (local storage, IP-based linking)
- **0.5-0.6**: Low-medium confidence (fingerprints, heuristics)

---

## Implementation Recommendations

### High Priority (Immediate Value)
1. ✅ **Email hash cross-matching** - Use `customers.email_hash` field
2. ✅ **IP-based cookie linking** - Already implemented
3. ✅ **Fingerprint fallback** - Already implemented

### Medium Priority (Next Phase)
4. **Local Storage ID** - Easy to implement, high persistence
5. **Session Storage ID** - Good for tab-scoped tracking
6. **Cross-device email linking** - Merge customers by email

### Lower Priority (Advanced Features)
7. **Device/Browser Fingerprinting** - Requires consent, privacy concerns
8. **Payment/Shipping Address** - Only available at checkout
9. **Social Media IDs** - Requires OAuth integration
10. **Order ID correlation** - Short-lived, complex

---

## Privacy Considerations

- **GDPR/CCPA Compliance**: Some methods (fingerprinting) may require consent
- **Hashing**: All PII (email, phone, addresses) should be hashed (SHA-256)
- **Data Minimization**: Only collect what's necessary
- **Consent**: Inform users about tracking methods used
- **Retention**: Set appropriate retention policies for identity data

---

## Schema Support

The current schema supports all these methods through:
- **`identities` table**: Stores any identity type with `type` and `value_hash`
- **`customers.email_hash`**: Direct email hash storage
- **`customer_identity_links`**: Links multiple identities with confidence scores
- **`sessions_tracking.ip`**: IP address for heuristic matching

No schema changes needed - the system is flexible enough to support all these methods!

