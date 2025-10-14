# Roster Assignment

[Link to Postman collection and necessary body fields](./Roster.postman_collection.json)

- Laravel 12
- tymon/jwt-auth

### Recommended Installation Prerequisites
1. Docker Compose
1. Make

#### Prerequisites
1. PHP 8.2+
1. MySQL 8+
1. Redis 8+

---
---

# Installation

1. `make dev-init`
2. `sudo make install`
3. `make perm`
    - `sudo make php`
    - `php artisan migrate --seed`

## Development Commands (Makefile)
- `sudo make php` - Access the PHP container
    - `php artisan test` - Run tests
    - `php artisan ide-helper:generate` - Generate IDE helper files
- `sudo make (up|down|restart)` - Manage containers

## Files / Folders
- `api` - The Laravel API application
- `docker` - Docker related files
- `envs` - Default environment files for development
- `Makefile` - Makefile for building / common commands

## Notes
- `envs.*.example` files can be directly copied and used
    - `cp envs/.env.api.example api/.env`
    - `cp envs/.env.example .env`
- Default DB & Redis credentials:
    - database=`laravel`
    - user=`dbuser`
    - password=`dbpass`
    - password=`asdf`

## API Route List

### Authentication Routes (Public)
- `POST /api/register` - Register a new supplier account
- `POST /api/login` - Authenticate and get access/refresh tokens. **Notes: Cookie Mode defaults to** `false` [learn more, auth.php](./api/config/auth.php#L5)
- `POST /api/auth/refresh` - Refresh access token using refresh token. [learn more, auth.php](./api/config/auth.php#L16)

### Authentication Routes (Protected)
- `POST /api/auth/logout` - Logout and invalidate tokens
- `GET /api/auth/me` - Get current authenticated user profile

### Vouche Routes (Protected)
- `GET /api/vouches` - List all vouches with pagination
- `POST /api/vouches` - Create a new vouche (vouch for another supplier)
- `DELETE /api/vouches/{id}` - Delete a vouche (only by the vouche-r)
- `GET /api/vouches/{supplier_id}` - Get all vouches for a specific supplier

#### Additional Context
- **Protected routes** require JWT access token in Authorization header: `Bearer {token}`
- All responses follow consistent JSON API format with `success`, `data`, and `message`. Additional `meta` and `pagination` fields may be included
- Vouches automatically use authenticated user as `vouched_by_id`
- Creating/deleting vouches automatically updates supplier `total_vouches` counter

---
---

# Indexing, Transaction Safety and Further Enhancements

## Recommended Database Indexes

### Current Implemented Indexes
- `suppliers.email` - Unique index (already implemented)
- `vouches.[vouched_by_id, vouched_for_id]` - Composite unique constraint (already implemented)
- `vouches.vouched_by_id` - Foreign key index (auto-created)
- `vouches.vouched_for_id` - Foreign key index (auto-created)

### Possible Indexes for Performance Enhancement
- `suppliers.total_vouches` - For sorting suppliers by popularity
- `vouches.created_at` - For chronological sorting and filtering
- `suppliers.created_at` - For registration date queries
- `suppliers.name` - For supplier name searches
- `vouches.vouched_for_id, vouches.created_at` - Composite index for supplier-specific vouche listing with date sorting

### Possible Further Index Usage Scenarios
- **Supplier ranking**: Sort by `total_vouches` for popularity features
- **Vouche timeline**: Filter vouches by date ranges
- **Search functionality**: Quick supplier name lookups
- **API pagination**: More efficient `ORDER BY created_at` queries

## Transaction Implementation

### Atomicity
- **Vouche Creation**: Uses `DB::transaction()` with 3 retry attempts
  - Creates vouche record
  - Increments supplier `total_vouches` counter
  - Rolls back both operations if either fails
  
- **Vouche Deletion**: Uses `DB::transaction()` with 3 retry attempts
  - Decrements supplier `total_vouches` counter
  - Deletes vouche record
  - Rolls back both operations if either fails

### Transaction Benefits
- **Data Consistency**: Counter always matches actual vouche count
- **Concurrent Safety**: Handles multiple users vouching simultaneously
- **Error Recovery**: Automatic rollback on database errors
- **Retry Logic**: 3 attempts to handle temporary deadlocks

### Database Constraints
- **Unique Constraint**: Prevents duplicate vouches between same suppliers
- **Foreign Keys**: Cascade delete vouches when suppliers are deleted (currently on application level, soft deletes implemented)
- **Soft Deletes**: Suppliers marked as deleted rather than removed

## Further Enhancements
- **Queue-based Counter Updates**: Offload counter increments/decrements to a background job queue.