# Database Schema Documentation

This document describes the database schema for the SaaS template application, including all entities, their attributes, and relationships.

## Overview

The application uses a multi-tenant architecture with organizations, user management, role-based permissions, and team invitations. The schema supports:

- User authentication with 2FA
- Organization-based multi-tenancy
- Role-based access control (RBAC) using Spatie Permission package
- Team invitations system
- Subscription management with Stripe integration
- Background job processing
- Session management and caching

## Core Entities

### Users Table

**Purpose**: Stores user account information and authentication data.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| `id` | bigint | Primary Key, Auto Increment | Unique user identifier |
| `organization_id` | bigint | Foreign Key, Nullable | Reference to user's organization |
| `name` | string | Required | User's full name |
| `email` | string | Required, Unique | User's email address |
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
└── has_many: Permissions (via model_has_permissions)

Organizations
├── has_many: Users
└── has_many: Invitations

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

## Migration Order

The migrations should be run in the following order (as indicated by timestamps):

1. `0001_01_01_000000_create_users_table.php` - Core user authentication
2. `0001_01_01_000001_create_cache_table.php` - Caching system
3. `0001_01_01_000002_create_jobs_table.php` - Background job system
4. `2025_08_26_100418_add_two_factor_columns_to_users_table.php` - 2FA support
5. `2025_10_18_185514_create_organizations_table.php` - Multi-tenancy
6. `2025_10_19_094254_create_invitations_table.php` - Team invitations
7. `2025_10_19_124050_create_permission_tables.php` - RBAC system

This order ensures proper foreign key relationships and dependencies are maintained.
