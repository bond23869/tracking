# Database Schema Documentation

This document describes the database schema for the SaaS template application, including all entities, their attributes, and relationships.

## Overview

The application uses a multi-tenant architecture with organizations, user management, role-based permissions, team invitations, and comprehensive event tracking. The schema supports:

- User authentication with 2FA and Google OAuth
- Organization-based multi-tenancy
- Role-based access control (RBAC) using Spatie Permission package
- Team invitations system
- Subscription management with Stripe integration
- Background job processing
- Session management and caching
- Event tracking and analytics
- Customer identity management
- Session and conversion tracking
- Attribution modeling
- Funnel analysis

## Core Entities

### Users Table

**Purpose**: Stores user account information and authentication data.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Unique user identifier |
| `organization_id` | bigint | Foreign Key, Nullable | Reference to user's organization |
| `name` | string | Required | User's full name |
| `email` | string | Required, Unique | User's email address |
| `google_id` | string | Nullable, Unique | Google OAuth identifier |
| `avatar` | string | Nullable | User avatar URL |
| `email_verified_at` | timestamp | Nullable | Email verification timestamp |
| `password` | string | Required | Hashed password |
| `two_factor_secret` | text | Nullable | 2FA secret key (encrypted) |
| `two_factor_recovery_codes` | text | Nullable | 2FA backup codes (encrypted) |
| `two_factor_confirmed_at` | timestamp | Nullable | 2FA setup confirmation timestamp |
| `remember_token` | string | Nullable | Remember me token |
| `created_at` | timestamp | Auto | Record creation timestamp |
| `updated_at` | timestamp | Auto | Record update timestamp |

**Relationships**:
- Belongs to one `Organization` (nullable for admin users)
- Has many `Invitations` as inviter
- Has many `Invitations` as accepter
- Has many `Roles` (via Spatie Permission)
- Has many `Permissions` (via Spatie Permission)
- Has many `Funnels` (as creator)

### Organizations Table

**Purpose**: Represents tenant organizations in the multi-tenant system.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Unique organization identifier |
| `name` | string | Required | Organization display name |
| `slug` | string | Required, Unique | URL-friendly organization identifier |
| `settings` | json | Nullable | Organization-specific configuration |
| `billing_status` | string | Default: 'active' | Billing status (active, suspended, cancelled) |
| `stripe_customer_id` | string | Nullable | Stripe customer ID for billing |
| `stripe_subscription_id` | string | Nullable | Stripe subscription ID |
| `plan` | string | Required | Current subscription plan |
| `trial_ends_at` | timestamp | Nullable | Trial period end date |
| `subscription_ends_at` | timestamp | Nullable | Subscription end date |
| `created_at` | timestamp | Auto | Record creation timestamp |
| `updated_at` | timestamp | Auto | Record update timestamp |

**Relationships**:
- Has many `Users`
- Has many `Invitations`
- Has many `Accounts`

### Accounts Table

**Purpose**: Represents customer accounts within organizations for tracking purposes.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Unique account identifier |
| `organization_id` | bigint | Foreign Key, Cascade Delete | Reference to organization |
| `name` | string | Required | Account display name |
| `slug` | string | Required, Unique | URL-friendly account identifier |
| `monthly_orders` | string | Default: 'less_than_500' | Monthly order volume category |
| `archived_at` | timestamp | Nullable | Account archival timestamp |
| `created_at` | timestamp | Auto | Record creation timestamp |
| `updated_at` | timestamp | Auto | Record update timestamp |

**Relationships**:
- Belongs to one `Organization`
- Has many `Websites`

### Invitations Table

**Purpose**: Manages team invitations for organizations.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Unique invitation identifier |
| `organization_id` | bigint | Foreign Key, Cascade Delete | Target organization |
| `invited_by_user_id` | bigint | Foreign Key, Cascade Delete | User who sent invitation |
| `email` | string | Required | Invitee's email address |
| `role` | string | Default: 'member' | Role to assign upon acceptance |
| `token` | string | Required, Unique | Unique invitation token |
| `expires_at` | timestamp | Required | Invitation expiration date |
| `accepted_at` | timestamp | Nullable | Acceptance timestamp |
| `accepted_by_user_id` | bigint | Foreign Key, Set Null | User who accepted invitation |
| `metadata` | json | Nullable | Additional invitation data |
| `created_at` | timestamp | Auto | Record creation timestamp |
| `updated_at` | timestamp | Auto | Record update timestamp |

**Indexes**:
- `(organization_id, email)` - Fast lookup for org invitations
- `(token, expires_at)` - Fast token validation
- `unique_org_email_invitation` - Prevents duplicate invitations

**Relationships**:
- Belongs to one `Organization`
- Belongs to one `User` (inviter)
- Belongs to one `User` (accepter, nullable)

## Permission System (Spatie Permission Package)

The application uses the Spatie Permission package for role-based access control.

### Permissions Table

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Unique permission identifier |
| `name` | string | Required | Permission name |
| `guard_name` | string | Required | Guard name (usually 'web') |
| `created_at` | timestamp | Auto | Record creation timestamp |
| `updated_at` | timestamp | Auto | Record update timestamp |

**Unique Constraint**: `(name, guard_name)`

### Roles Table

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Unique role identifier |
| `name` | string | Required | Role name |
| `guard_name` | string | Required | Guard name (usually 'web') |
| `created_at` | timestamp | Auto | Record creation timestamp |
| `updated_at` | timestamp | Auto | Record update timestamp |

**Unique Constraint**: `(name, guard_name)`

### Model Has Permissions Table

**Purpose**: Links models (users) to permissions directly.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `permission_id` | bigint | Foreign Key | Reference to permission |
| `model_type` | string | Required | Model class name |
| `model_id` | bigint | Required | Model instance ID |

**Primary Key**: `(permission_id, model_id, model_type)`

### Model Has Roles Table

**Purpose**: Links models (users) to roles.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `role_id` | bigint | Foreign Key | Reference to role |
| `model_type` | string | Required | Model class name |
| `model_id` | bigint | Required | Model instance ID |

**Primary Key**: `(role_id, model_id, model_type)`

### Role Has Permissions Table

**Purpose**: Links roles to permissions.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `permission_id` | bigint | Foreign Key | Reference to permission |
| `role_id` | bigint | Foreign Key | Reference to role |

**Primary Key**: `(permission_id, role_id)`

## Tracking Entities

### Websites Table

**Purpose**: Represents tracked websites within accounts.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Unique website identifier |
| `organization_id` | bigint | Foreign Key, Cascade Delete | Reference to organization |
| `account_id` | bigint | Foreign Key, Cascade Delete | Reference to account |
| `name` | string | Required | Website display name |
| `url` | string | Required | Website URL |
| `status` | string | Default: 'active' | Website status |
| `connection_status` | string | Default: 'connected' | Connection status |
| `connection_error` | string | Nullable | Connection error message |
| `created_at` | timestamp | Auto | Record creation timestamp |
| `updated_at` | timestamp | Auto | Record update timestamp |

**Relationships**:
- Belongs to one `Organization`
- Belongs to one `Account`
- Has many `IngestionTokens`
- Has many `Identities`
- Has many `Customers`
- Has many `Sessions` (sessions_tracking)
- Has many `Events`
- Has many `Touches`
- Has many `Conversions`
- Has many `LandingPages`
- Has many `ReferrerDomains`
- Has many `UtmSources`, `UtmMediums`, `UtmCampaigns`, `UtmTerms`, `UtmContents`
- Has many `Funnels`
- Has many `WebsitePixels`

### Ingestion Tokens Table

**Purpose**: API tokens for event ingestion with scoped permissions.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Unique token identifier |
| `website_id` | bigint | Foreign Key, Cascade Delete | Reference to website |
| `name` | string | Required | Token name |
| `token_prefix` | string(12) | Indexed | Token prefix for identification |
| `token_hash` | string | Required | Hashed token value |
| `scopes` | json | Nullable | Token permission scopes |
| `ip_allowlist` | json | Nullable | Allowed IP addresses |
| `last_used_at` | timestamp | Nullable | Last usage timestamp |
| `expires_at` | timestamp | Nullable | Token expiration date |
| `revoked_at` | timestamp | Nullable | Token revocation timestamp |
| `created_at` | timestamp | Auto | Record creation timestamp |
| `updated_at` | timestamp | Auto | Record update timestamp |

**Unique Constraint**: `(website_id, name)`

**Relationships**:
- Belongs to one `Website`
- Has many `Events`
- Has many `TokenUsages`

### Identities Table

**Purpose**: Stores anonymous identity identifiers (cookies, user IDs, email hashes, etc.).

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Unique identity identifier |
| `website_id` | bigint | Foreign Key, Cascade Delete | Reference to website |
| `type` | string | Required | Identity type (cookie, user_id, email_hash, ga_cid, etc.) |
| `value_hash` | string | Required | Hashed identity value |
| `first_seen_at` | timestamp | Nullable | First appearance timestamp |
| `last_seen_at` | timestamp | Nullable | Last appearance timestamp |
| `created_at` | timestamp | Auto | Record creation timestamp |
| `updated_at` | timestamp | Auto | Record update timestamp |

**Unique Constraint**: `(website_id, type, value_hash)`

**Indexes**:
- `(website_id, type)` - Fast lookup by website and type

**Relationships**:
- Belongs to one `Website`
- Belongs to many `Customers` (via customer_identity_links)

### Customers Table

**Purpose**: Represents tracked customers across multiple sessions and devices.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Unique customer identifier |
| `website_id` | bigint | Foreign Key, Cascade Delete | Reference to website |
| `first_seen_at` | timestamp | Nullable | First appearance timestamp |
| `last_seen_at` | timestamp | Nullable | Last appearance timestamp |
| `first_touch_id` | bigint | Nullable | Reference to first touch event |
| `last_touch_id` | bigint | Nullable | Reference to last touch event |
| `email_hash` | string | Nullable | Hashed email address |
| `status` | string | Default: 'active' | Customer status |
| `created_at` | timestamp | Auto | Record creation timestamp |
| `updated_at` | timestamp | Auto | Record update timestamp |

**Indexes**:
- `(website_id)` - Fast lookup by website

**Relationships**:
- Belongs to one `Website`
- Has many `Sessions` (sessions_tracking)
- Has many `Events`
- Has many `Touches`
- Has many `Conversions`
- Belongs to many `Identities` (via customer_identity_links)

### Customer Identity Links Table

**Purpose**: Links customers to multiple identity sources with confidence scores.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Unique link identifier |
| `customer_id` | bigint | Foreign Key, Cascade Delete | Reference to customer |
| `identity_id` | bigint | Foreign Key, Cascade Delete | Reference to identity |
| `confidence` | decimal(5,4) | Default: 1.0000 | Link confidence score (0-1) |
| `source` | string | Nullable | Link source (login, heuristic, sdk) |
| `first_seen_at` | timestamp | Nullable | First link appearance |
| `last_seen_at` | timestamp | Nullable | Last link appearance |
| `created_at` | timestamp | Auto | Record creation timestamp |
| `updated_at` | timestamp | Auto | Record update timestamp |

**Unique Constraint**: `(customer_id, identity_id)`

**Indexes**:
- `(identity_id)` - Fast lookup by identity

**Relationships**:
- Belongs to one `Customer`
- Belongs to one `Identity`

### Sessions Tracking Table

**Purpose**: Tracks user sessions with attribution data.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Unique session identifier |
| `website_id` | bigint | Foreign Key, Cascade Delete | Reference to website |
| `customer_id` | bigint | Foreign Key, Cascade Delete | Reference to customer |
| `started_at` | timestamp | Required | Session start timestamp |
| `ended_at` | timestamp | Nullable | Session end timestamp |
| `duration_ms` | unsigned integer | Nullable | Session duration in milliseconds |
| `landing_page_id` | bigint | Foreign Key, Nullable | Reference to landing page |
| `referrer_domain_id` | bigint | Foreign Key, Nullable | Reference to referrer domain |
| `utm_source_id` | bigint | Foreign Key, Nullable | UTM source reference |
| `utm_medium_id` | bigint | Foreign Key, Nullable | UTM medium reference |
| `utm_campaign_id` | bigint | Foreign Key, Nullable | UTM campaign reference |
| `utm_term_id` | bigint | Foreign Key, Nullable | UTM term reference |
| `utm_content_id` | bigint | Foreign Key, Nullable | UTM content reference |
| `landing_url` | text | Nullable | Full landing URL |
| `referrer_url` | text | Nullable | Full referrer URL |
| `ip` | string(45) | Nullable | Client IP address |
| `user_agent` | text | Nullable | Client user agent |
| `is_bot` | boolean | Default: false | Bot detection flag |
| `is_bounced` | boolean | Default: false | Bounce detection flag |
| `created_at` | timestamp | Auto | Record creation timestamp |
| `updated_at` | timestamp | Auto | Record update timestamp |

**Indexes**:
- `(website_id, started_at)` - Fast lookup by website and time
- `(customer_id, started_at)` - Fast lookup by customer and time

**Relationships**:
- Belongs to one `Website`
- Belongs to one `Customer`
- Belongs to one `LandingPage` (nullable)
- Belongs to one `ReferrerDomain` (nullable)
- Has many `Events`
- Has many `Touches`
- Has many `Conversions`

### Events Table

**Purpose**: Stores all tracked events with properties and revenue data.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Unique event identifier |
| `website_id` | bigint | Foreign Key, Cascade Delete | Reference to website |
| `session_id` | bigint | Foreign Key, Nullable | Reference to session |
| `customer_id` | bigint | Foreign Key, Nullable | Reference to customer |
| `name` | string | Required | Event name |
| `occurred_at` | timestamp | Required | Event timestamp |
| `props` | json | Nullable | Event properties |
| `revenue_cents` | integer | Nullable | Revenue in cents |
| `currency` | string(3) | Nullable | Currency code |
| `idempotency_key` | string | Required, Unique | Event deduplication key |
| `ingestion_token_id` | bigint | Foreign Key, Nullable | Token used for ingestion |
| `schema_version` | unsigned integer | Default: 1 | Event schema version |
| `sdk_version` | string | Nullable | SDK version |
| `utm_source_id` | bigint | Foreign Key, Nullable | UTM source reference |
| `utm_medium_id` | bigint | Foreign Key, Nullable | UTM medium reference |
| `utm_campaign_id` | bigint | Foreign Key, Nullable | UTM campaign reference |
| `utm_term_id` | bigint | Foreign Key, Nullable | UTM term reference |
| `utm_content_id` | bigint | Foreign Key, Nullable | UTM content reference |
| `referrer_domain_id` | bigint | Foreign Key, Nullable | Referrer domain reference |
| `landing_page_id` | bigint | Foreign Key, Nullable | Landing page reference |
| `created_at` | timestamp | Auto | Record creation timestamp |
| `updated_at` | timestamp | Auto | Record update timestamp |

**Indexes**:
- `(website_id, occurred_at)` - Fast lookup by website and time
- `(session_id, occurred_at)` - Fast lookup by session and time
- `(customer_id, occurred_at)` - Fast lookup by customer and time
- `(name, website_id, occurred_at)` - Fast lookup by event name

**Relationships**:
- Belongs to one `Website`
- Belongs to one `Session` (nullable)
- Belongs to one `Customer` (nullable)
- Belongs to one `IngestionToken` (nullable)
- Has one `EventDedupKey`
- Has one `Conversion` (if conversion event)

### Event Dedup Keys Table

**Purpose**: Ensures event idempotency by tracking processed idempotency keys.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Unique dedup key identifier |
| `idempotency_key` | string | Required, Unique | Event idempotency key |
| `event_id` | bigint | Foreign Key, Cascade Delete | Reference to event |
| `created_at` | timestamp | Auto | Record creation timestamp |

**Relationships**:
- Belongs to one `Event`

### Touches Table

**Purpose**: Tracks marketing touches for attribution modeling.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Unique touch identifier |
| `website_id` | bigint | Foreign Key, Cascade Delete | Reference to website |
| `customer_id` | bigint | Foreign Key, Cascade Delete | Reference to customer |
| `session_id` | bigint | Foreign Key, Nullable | Reference to session |
| `occurred_at` | timestamp | Required | Touch timestamp |
| `type` | string | Required | Touch type (landing, ad_click, email_open, etc.) |
| `utm_source_id` | bigint | Foreign Key, Nullable | UTM source reference |
| `utm_medium_id` | bigint | Foreign Key, Nullable | UTM medium reference |
| `utm_campaign_id` | bigint | Foreign Key, Nullable | UTM campaign reference |
| `utm_term_id` | bigint | Foreign Key, Nullable | UTM term reference |
| `utm_content_id` | bigint | Foreign Key, Nullable | UTM content reference |
| `referrer_domain_id` | bigint | Foreign Key, Nullable | Referrer domain reference |
| `landing_page_id` | bigint | Foreign Key, Nullable | Landing page reference |
| `source_event_id` | bigint | Foreign Key, Nullable | Source event reference |
| `metadata` | json | Nullable | Additional touch metadata |
| `created_at` | timestamp | Auto | Record creation timestamp |
| `updated_at` | timestamp | Auto | Record update timestamp |

**Indexes**:
- `(website_id, customer_id, occurred_at)` - Fast lookup by website, customer, and time

**Relationships**:
- Belongs to one `Website`
- Belongs to one `Customer`
- Belongs to one `Session` (nullable)
- Has many `Conversions` (as first_touch, last_non_direct_touch, or attributed_touch)

### Conversions Table

**Purpose**: Tracks conversion events with attribution to marketing touches.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Unique conversion identifier |
| `website_id` | bigint | Foreign Key, Cascade Delete | Reference to website |
| `customer_id` | bigint | Foreign Key, Cascade Delete | Reference to customer |
| `session_id` | bigint | Foreign Key, Nullable | Reference to session |
| `event_id` | bigint | Foreign Key, Cascade Delete | Reference to conversion event |
| `occurred_at` | timestamp | Required | Conversion timestamp |
| `value_cents` | integer | Nullable | Conversion value in cents |
| `currency` | string(3) | Nullable | Currency code |
| `first_touch_id` | bigint | Foreign Key, Nullable | First touch attribution |
| `last_non_direct_touch_id` | bigint | Foreign Key, Nullable | Last non-direct touch attribution |
| `attributed_touch_id` | bigint | Foreign Key, Nullable | Attributed touch (based on model) |
| `attribution_model` | string | Nullable | Attribution model used (first_touch, last_non_direct, etc.) |
| `attribution_weight` | json | Nullable | Attribution weights for multi-touch models |
| `created_at` | timestamp | Auto | Record creation timestamp |
| `updated_at` | timestamp | Auto | Record update timestamp |

**Indexes**:
- `(website_id, occurred_at)` - Fast lookup by website and time
- `(customer_id, occurred_at)` - Fast lookup by customer and time

**Relationships**:
- Belongs to one `Website`
- Belongs to one `Customer`
- Belongs to one `Session` (nullable)
- Belongs to one `Event`
- Belongs to one `Touch` (first_touch, nullable)
- Belongs to one `Touch` (last_non_direct_touch, nullable)
- Belongs to one `Touch` (attributed_touch, nullable)

### Landing Pages Table

**Purpose**: Tracks unique landing pages for attribution analysis.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Unique landing page identifier |
| `website_id` | bigint | Foreign Key, Cascade Delete | Reference to website |
| `path` | string | Required | Page path |
| `query_hash` | string(64) | Default: '' | Query string hash |
| `full_url_sample` | text | Nullable | Sample full URL |
| `first_seen_at` | timestamp | Nullable | First appearance timestamp |
| `created_at` | timestamp | Auto | Record creation timestamp |
| `updated_at` | timestamp | Auto | Record update timestamp |

**Unique Constraint**: `(website_id, path, query_hash)`

**Indexes**:
- `(website_id)` - Fast lookup by website

**Relationships**:
- Belongs to one `Website`
- Has many `Sessions`
- Has many `Events`
- Has many `Touches`

### Referrer Domains Table

**Purpose**: Tracks referrer domains with categorization.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Unique referrer domain identifier |
| `website_id` | bigint | Foreign Key, Cascade Delete | Reference to website |
| `domain` | string | Required | Referrer domain |
| `category` | string | Nullable | Domain category (search, social, email, direct, other) |
| `first_seen_at` | timestamp | Nullable | First appearance timestamp |
| `created_at` | timestamp | Auto | Record creation timestamp |
| `updated_at` | timestamp | Auto | Record update timestamp |

**Unique Constraint**: `(website_id, domain)`

**Indexes**:
- `(website_id, category)` - Fast lookup by website and category

**Relationships**:
- Belongs to one `Website`
- Has many `Sessions`
- Has many `Events`
- Has many `Touches`

### UTM Parameter Tables

**Purpose**: Normalized storage of UTM parameters (source, medium, campaign, term, content).

#### Utm Sources Table

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Unique UTM source identifier |
| `website_id` | bigint | Foreign Key, Cascade Delete | Reference to website |
| `value` | string | Required | UTM source value |
| `first_seen_at` | timestamp | Nullable | First appearance timestamp |
| `created_at` | timestamp | Auto | Record creation timestamp |
| `updated_at` | timestamp | Auto | Record update timestamp |

**Unique Constraint**: `(website_id, value)`

#### Utm Mediums Table

Same structure as Utm Sources, but for UTM medium values.

#### Utm Campaigns Table

Same structure as Utm Sources, but for UTM campaign values.

#### Utm Terms Table

Same structure as Utm Sources, but for UTM term values.

#### Utm Contents Table

Same structure as Utm Sources, but for UTM content values.

**Relationships** (all UTM tables):
- Belongs to one `Website`
- Has many `Sessions`
- Has many `Events`
- Has many `Touches`

### Funnels Table

**Purpose**: Defines conversion funnels for analysis.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Unique funnel identifier |
| `website_id` | bigint | Foreign Key, Cascade Delete | Reference to website |
| `name` | string | Required | Funnel name |
| `window_seconds` | unsigned integer | Default: 0 | Funnel time window (0 = unlimited) |
| `definition` | json | Nullable | Full funnel definition snapshot |
| `created_by_user_id` | bigint | Foreign Key, Nullable | User who created the funnel |
| `created_at` | timestamp | Auto | Record creation timestamp |
| `updated_at` | timestamp | Auto | Record update timestamp |

**Unique Constraint**: `(website_id, name)`

**Relationships**:
- Belongs to one `Website`
- Belongs to one `User` (creator, nullable)
- Has many `FunnelSteps`

### Funnel Steps Table

**Purpose**: Defines individual steps within a funnel.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Unique step identifier |
| `funnel_id` | bigint | Foreign Key, Cascade Delete | Reference to funnel |
| `step_order` | unsigned integer | Required | Step order in funnel |
| `name` | string | Required | Step name |
| `match` | json | Required | Event matching criteria (event name + filters) |
| `created_at` | timestamp | Auto | Record creation timestamp |
| `updated_at` | timestamp | Auto | Record update timestamp |

**Unique Constraint**: `(funnel_id, step_order)`

**Relationships**:
- Belongs to one `Funnel`

### Token Usages Table

**Purpose**: Tracks API token usage for monitoring and analytics.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Unique usage identifier |
| `ingestion_token_id` | bigint | Foreign Key, Cascade Delete | Reference to token |
| `website_id` | bigint | Foreign Key, Cascade Delete | Reference to website |
| `occurred_at` | timestamp | Required | Usage timestamp |
| `ip` | string(45) | Nullable | Client IP address |
| `success` | boolean | Default: true | Request success status |
| `error_code` | string | Nullable | Error code if failed |
| `request_id` | string | Nullable | Request identifier |
| `created_at` | timestamp | Auto | Record creation timestamp |
| `updated_at` | timestamp | Auto | Record update timestamp |

**Indexes**:
- `(ingestion_token_id, occurred_at)` - Fast lookup by token and time
- `(website_id, occurred_at)` - Fast lookup by website and time

**Relationships**:
- Belongs to one `IngestionToken`
- Belongs to one `Website`

### Website Pixels Table

**Purpose**: Stores pixel tracking configurations for websites.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Unique pixel identifier |
| `website_id` | bigint | Foreign Key, Cascade Delete | Reference to website |
| `pixel_id` | string | Required | Pixel identifier |
| `access_token` | string | Required | Pixel access token |
| `created_at` | timestamp | Auto | Record creation timestamp |
| `updated_at` | timestamp | Auto | Record update timestamp |

**Relationships**:
- Belongs to one `Website`

## System Tables

### Sessions Table

**Purpose**: Stores user session data.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | string | Primary Key | Session identifier |
| `user_id` | bigint | Foreign Key, Nullable, Indexed | Associated user |
| `ip_address` | string(45) | Nullable | Client IP address |
| `user_agent` | text | Nullable | Client user agent |
| `payload` | longtext | Required | Session data |
| `last_activity` | integer | Required, Indexed | Last activity timestamp |

### Password Reset Tokens Table

**Purpose**: Stores password reset tokens.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `email` | string | Primary Key | User's email address |
| `token` | string | Required | Reset token |
| `created_at` | timestamp | Nullable | Token creation timestamp |

### Cache Tables

#### Cache Table

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `key` | string | Primary Key | Cache key |
| `value` | mediumtext | Required | Cached value |
| `expiration` | integer | Required | Expiration timestamp |

#### Cache Locks Table

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `key` | string | Primary Key | Lock key |
| `owner` | string | Required | Lock owner |
| `expiration` | integer | Required | Lock expiration |

### Job System Tables

#### Jobs Table

**Purpose**: Stores queued jobs for background processing.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Job identifier |
| `queue` | string | Required, Indexed | Queue name |
| `payload` | longtext | Required | Job data |
| `attempts` | tinyint unsigned | Required | Attempt count |
| `reserved_at` | integer unsigned | Nullable | Reservation timestamp |
| `available_at` | integer unsigned | Required | Available timestamp |
| `created_at` | integer unsigned | Required | Creation timestamp |

#### Job Batches Table

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | string | Primary Key | Batch identifier |
| `name` | string | Required | Batch name |
| `total_jobs` | integer | Required | Total jobs in batch |
| `pending_jobs` | integer | Required | Pending jobs count |
| `failed_jobs` | integer | Required | Failed jobs count |
| `failed_job_ids` | longtext | Required | Failed job IDs |
| `options` | mediumtext | Nullable | Batch options |
| `cancelled_at` | integer | Nullable | Cancellation timestamp |
| `created_at` | integer | Required | Creation timestamp |
| `finished_at` | integer | Nullable | Completion timestamp |

#### Failed Jobs Table

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Failed job identifier |
| `uuid` | string | Required, Unique | Job UUID |
| `connection` | text | Required | Queue connection |
| `queue` | text | Required | Queue name |
| `payload` | longtext | Required | Job data |
| `exception` | longtext | Required | Exception details |
| `failed_at` | timestamp | Default: CURRENT_TIMESTAMP | Failure timestamp |

## Entity Relationships Diagram

```
Users
├── belongs_to: Organization (nullable)
├── has_many: Invitations (as inviter)
├── has_many: Invitations (as accepter)
├── has_many: Roles (via model_has_roles)
├── has_many: Permissions (via model_has_permissions)
└── has_many: Funnels (as creator)

Organizations
├── has_many: Users
├── has_many: Invitations
└── has_many: Accounts

Accounts
├── belongs_to: Organization
└── has_many: Websites

Websites
├── belongs_to: Organization
├── belongs_to: Account
├── has_many: IngestionTokens
├── has_many: Identities
├── has_many: Customers
├── has_many: Sessions (sessions_tracking)
├── has_many: Events
├── has_many: Touches
├── has_many: Conversions
├── has_many: LandingPages
├── has_many: ReferrerDomains
├── has_many: UtmSources, UtmMediums, UtmCampaigns, UtmTerms, UtmContents
├── has_many: Funnels
└── has_many: WebsitePixels

IngestionTokens
├── belongs_to: Website
├── has_many: Events
└── has_many: TokenUsages

Identities
├── belongs_to: Website
└── belongs_to_many: Customers (via customer_identity_links)

Customers
├── belongs_to: Website
├── has_many: Sessions (sessions_tracking)
├── has_many: Events
├── has_many: Touches
├── has_many: Conversions
└── belongs_to_many: Identities (via customer_identity_links)

CustomerIdentityLinks
├── belongs_to: Customer
└── belongs_to: Identity

Sessions (sessions_tracking)
├── belongs_to: Website
├── belongs_to: Customer
├── belongs_to: LandingPage (nullable)
├── belongs_to: ReferrerDomain (nullable)
├── has_many: Events
├── has_many: Touches
└── has_many: Conversions

Events
├── belongs_to: Website
├── belongs_to: Session (nullable)
├── belongs_to: Customer (nullable)
├── belongs_to: IngestionToken (nullable)
├── has_one: EventDedupKey
└── has_one: Conversion

EventDedupKeys
└── belongs_to: Event

Touches
├── belongs_to: Website
├── belongs_to: Customer
├── belongs_to: Session (nullable)
├── has_many: Conversions (as first_touch, last_non_direct_touch, attributed_touch)

Conversions
├── belongs_to: Website
├── belongs_to: Customer
├── belongs_to: Session (nullable)
├── belongs_to: Event
├── belongs_to: Touch (first_touch, nullable)
├── belongs_to: Touch (last_non_direct_touch, nullable)
└── belongs_to: Touch (attributed_touch, nullable)

LandingPages
├── belongs_to: Website
├── has_many: Sessions
├── has_many: Events
└── has_many: Touches

ReferrerDomains
├── belongs_to: Website
├── has_many: Sessions
├── has_many: Events
└── has_many: Touches

UtmSources, UtmMediums, UtmCampaigns, UtmTerms, UtmContents
├── belongs_to: Website
├── has_many: Sessions
├── has_many: Events
└── has_many: Touches

Funnels
├── belongs_to: Website
├── belongs_to: User (creator, nullable)
└── has_many: FunnelSteps

FunnelSteps
└── belongs_to: Funnel

TokenUsages
├── belongs_to: IngestionToken
└── belongs_to: Website

WebsitePixels
└── belongs_to: Website

Invitations
├── belongs_to: Organization
├── belongs_to: User (inviter)
└── belongs_to: User (accepter, nullable)

Roles
├── has_many: Users (via model_has_roles)
└── has_many: Permissions (via role_has_permissions)

Permissions
├── has_many: Users (via model_has_permissions)
└── has_many: Roles (via role_has_permissions)
```

## Key Features

### Multi-Tenancy
- Organizations serve as tenant boundaries
- Users belong to organizations (except admin users)
- Data isolation through organization_id foreign keys

### Authentication & Security
- Email/password authentication
- Two-factor authentication (2FA) support
- Session management
- Password reset functionality

### Authorization
- Role-based access control using Spatie Permission
- Direct permission assignment to users
- Role-based permission inheritance
- Guard-based permission scoping

### Team Management
- Organization-based team invitations
- Token-based invitation system
- Role assignment during invitation
- Expiration and acceptance tracking

### Subscription Management
- Stripe integration for billing
- Trial period support
- Multiple subscription plans
- Billing status tracking

### Background Processing
- Queue-based job system
- Job batching support
- Failed job tracking
- Multiple queue support

### Event Tracking
- Comprehensive event ingestion via API tokens
- Event deduplication using idempotency keys
- Revenue tracking with multi-currency support
- Schema versioning for event evolution

### Customer Identity Management
- Multi-identity linking (cookies, user IDs, email hashes, etc.)
- Confidence scoring for identity links
- Cross-device and cross-session customer recognition
- Identity source tracking (login, heuristic, SDK)

### Session Tracking
- Full session lifecycle tracking
- Bot detection and filtering
- Bounce detection
- Attribution data capture (UTM parameters, referrers, landing pages)

### Attribution Modeling
- First touch attribution
- Last non-direct touch attribution
- Custom attribution models
- Multi-touch attribution support (with weights)
- Touch tracking for marketing campaigns

### Conversion Analysis
- Conversion event tracking
- Revenue attribution to marketing touches
- Multiple attribution models per conversion
- Time-windowed conversion tracking

### Funnel Analysis
- Custom funnel definition
- Step-by-step conversion tracking
- Time-windowed funnel analysis
- Event-based step matching with filters

### API Token Management
- Scoped token permissions
- IP allowlisting
- Token expiration and revocation
- Usage tracking and monitoring

## Migration Order

The migrations should be run in the following order (as indicated by timestamps):

### Core System
1. `0001_01_01_000000_create_users_table.php` - Core user authentication
2. `0001_01_01_000001_create_cache_table.php` - Caching system
3. `0001_01_01_000002_create_jobs_table.php` - Background job system
4. `2025_08_26_100418_add_two_factor_columns_to_users_table.php` - 2FA support
5. `2025_10_18_185514_create_organizations_table.php` - Multi-tenancy
6. `2025_10_19_094254_create_invitations_table.php` - Team invitations
7. `2025_10_19_124050_create_permission_tables.php` - RBAC system
8. `2025_10_19_161925_add_google_id_to_users_table.php` - Google OAuth support

### Tracking Infrastructure
9. `2025_10_31_125212_add_accounts_table.php` - Account management
10. `2025_10_31_125819_create_websites_table.php` - Website tracking setup
11. `2025_10_31_125946_create_website_pixels_table.php` - Pixel tracking
12. `2025_11_01_120000_create_ingestion_tokens_table.php` - API tokens

### Identity & Customer Management
13. `2025_11_01_121100_create_identities_table.php` - Identity tracking
14. `2025_11_01_121000_create_customers_table.php` - Customer records
15. `2025_11_01_121200_create_customer_identity_links_table.php` - Identity linking

### Tracking Dimensions
16. `2025_11_01_121400_create_landing_pages_table.php` - Landing page tracking
17. `2025_11_01_121300_create_referrer_domains_table.php` - Referrer tracking
18. `2025_11_01_121500_create_utm_sources_table.php` - UTM source tracking
19. `2025_11_01_121510_create_utm_mediums_table.php` - UTM medium tracking
20. `2025_11_01_121520_create_utm_campaigns_table.php` - UTM campaign tracking
21. `2025_11_01_121530_create_utm_terms_table.php` - UTM term tracking
22. `2025_11_01_121540_create_utm_contents_table.php` - UTM content tracking

### Core Tracking Events
23. `2025_11_01_121600_create_sessions_table.php` - Session tracking
24. `2025_11_01_121700_create_events_table.php` - Event tracking
25. `2025_11_01_121800_create_touches_table.php` - Marketing touch tracking
26. `2025_11_01_121900_create_conversions_table.php` - Conversion tracking

### Analysis & Monitoring
27. `2025_11_01_122000_create_funnels_table.php` - Funnel analysis
28. `2025_11_01_122100_create_funnel_steps_table.php` - Funnel step definitions
29. `2025_11_01_122200_create_event_dedup_keys_table.php` - Event deduplication
30. `2025_11_01_122300_create_token_usages_table.php` - Token usage monitoring

### Updates
31. `2025_11_03_092236_add_archived_at_to_accounts_table.php` - Account archival

This order ensures proper foreign key relationships and dependencies are maintained.
