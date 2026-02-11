# Notification System

A production-ready, multi-tenant notification system built with Laravel. This system demonstrates scalable architecture patterns including queues, caching, rate limiting, and robust error handling.

## Features

- **Multi-tenant Support**: Header-based tenant isolation with automatic data scoping
- **Queue Processing**: Asynchronous notification processing with Redis queue driver
- **Rate Limiting**: Configurable per-tenant, per-user rate limiting (default: 10 notifications/hour)
- **Caching**: Tag-based caching with automatic invalidation for optimal performance
- **Retry Logic**: Exponential backoff retry mechanism for failed notifications
- **Scheduled Notifications**: Support for future-dated notification delivery
- **Monitoring APIs**: Endpoints for retrieving recent notifications and summary statistics
- **Extensible Channels**: Easy addition of new notification channels (log, email, SMS)

## Prerequisites

- PHP 8.2 or higher
- Composer
- Docker and Docker Compose (or Laravel Sail)
- MySQL 8.0+
- Redis

## Setup Instructions

### 1. Clone the Repository

```bash
git clone https://github.com/BishalGurung360/GlobalyHub-Assessment
cd globalyhub-assessment
```

### 2. Environment Configuration

Copy the `.env.example` file to `.env`:

```bash
cp .env.example .env
```

The `.env.example` file is pre-configured with Docker container names and optimal settings. No additional configuration is required.

### 3. Start Docker Containers

First build the docker image. This might take a while:

```bash
docker compose build
```

Start the Docker containers using Docker Compose:

```bash
docker compose up -d
```

Or if using Laravel Sail:

```bash
./vendor/bin/sail up -d
```

This will start the following services:
- Application container (`globalyhub`)
- MySQL database (`globalyhub.mysql`)
- Redis (`globalyhub.redis`)
- MailHog (for email testing)
- phpMyAdmin (optional)

### 4. Install Dependencies

**Important**: All commands must be run from inside the Docker container or using Laravel Sail.

Using Docker:
```bash
docker compose exec globalyhub composer install
```

Or using Laravel Sail:
```bash
./vendor/bin/sail composer install
```

### 5. Generate Application Key

```bash
docker compose exec globalyhub php artisan key:generate
```

Or using Laravel Sail:
```bash
./vendor/bin/sail artisan key:generate
```

### 6. Run Database Migrations

```bash
docker compose exec globalyhub php artisan migrate
```

Or using Laravel Sail:
```bash
./vendor/bin/sail artisan migrate
```

### 7. Seed the Database (Optional)

```bash
docker compose exec globalyhub php artisan db:seed
```

Or using Laravel Sail:
```bash
./vendor/bin/sail artisan db:seed
```

This will run the `UserSeeder` to create sample users for testing.

## Environment Configuration

### Key Environment Variables

The following environment variables are configured in `.env.example`:

#### Database Configuration
```env
DB_CONNECTION=mysql
DB_HOST=globalyhub.mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=secret
```

**Note**: `DB_HOST` and `REDIS_HOST` use Docker container names (`globalyhub.mysql` and `globalyhub.redis`) for internal Docker networking.

#### Queue Configuration
```env
QUEUE_CONNECTION=redis
```

The system uses Redis for queue processing. Ensure Redis is running before processing notifications.

#### Cache Configuration
```env
CACHE_STORE=redis
```

Redis is used for caching to optimize performance.

#### Redis Configuration
```env
REDIS_CLIENT=phpredis
REDIS_HOST=globalyhub.redis
REDIS_PASSWORD=null
REDIS_PORT=6379
```

#### Notification Rate Limiting
```env
NOTIFICATION_RATE_LIMIT_MAX_ATTEMPTS=10
NOTIFICATION_RATE_LIMIT_DECAY_SECONDS=3600
```

These variables control the rate limiting:
- `NOTIFICATION_RATE_LIMIT_MAX_ATTEMPTS`: Maximum notifications per user per tenant (default: 10)
- `NOTIFICATION_RATE_LIMIT_DECAY_SECONDS`: Time window in seconds (default: 3600 = 1 hour)

## Database Setup

### Migrations

The following migrations are included:

1. `0001_01_01_000000_create_users_table.php` - Creates users table
2. `0001_01_01_000001_create_cache_table.php` - Creates cache table
3. `0001_01_01_000002_create_jobs_table.php` - Creates jobs, job_batches, and failed_jobs tables
4. `0001_01_01_000003_create_notifications_table.php` - Creates notifications table with indexes
5. `2025_02_10_000001_add_created_at_index_to_notifications_table.php` - Adds index on `created_at` for performance

### Database Schema

The `notifications` table includes:
- `uuid` (unique identifier)
- `tenant_id` (for multi-tenant isolation)
- `user_id` (foreign key to users)
- `channel` (log, email, sms)
- `title` and `body` (notification content)
- `payload` (JSON for additional data)
- `status` (pending, processing, sent, failed, cancelled)
- `attempts` and `max_attempts` (for retry logic)
- `scheduled_at` (for scheduled notifications)
- `processed_at`, `failed_at`, `last_error` (for tracking)
- Timestamps (`created_at`, `updated_at`)

### Seeding

Run the seeder to create sample users:

```bash
docker compose exec globalyhub php artisan db:seed
```

Or using Laravel Sail:
```bash
./vendor/bin/sail artisan db:seed
```

## Queue Configuration

### Starting Queue Workers

**Important**: Queue workers must be running for notifications to be processed.

Start a queue worker:

```bash
docker compose exec globalyhub php artisan queue:work
```

Or using Laravel Sail:
```bash
./vendor/bin/sail artisan queue:work
```

### Queue Features

- **Redis Driver**: Uses Laravel's Redis queue driver (Redis Lists)
- **Retry Logic**: Exponential backoff (3^n seconds, max 600 seconds)
- **Max Attempts**: Configurable per notification (default: 3, max: 10)
- **Failed Jobs**: Automatically tracked in `failed_jobs` table

### Production Considerations

For production environments, use a process manager like Supervisor to keep queue workers running:

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/worker.log
stopwaitsecs=3600
```

## Testing Instructions

### Running Tests

Run the test suite:

```bash
docker compose exec globalyhub php artisan test
```

Or using Laravel Sail:
```bash
./vendor/bin/sail artisan test
```

Or using PHPUnit directly:
```bash
./vendor/bin/phpunit
```

### Test Coverage

The test suite includes **13 feature tests** covering:

**NotificationControllerTest** (7 tests):
- Successful notification creation
- Tenant ID header requirement
- Validation errors
- Rate limit enforcement
- Rate limit reset
- Queue dispatch verification
- Multi-tenant isolation

**NotificationMonitoringControllerTest** (6 tests):
- Recent notifications retrieval
- Filtering by user_id
- Tenant scoping
- Cache invalidation
- Summary statistics
- Channel breakdown

**Note**: Tests use the `RefreshDatabase` trait to ensure a clean database state for each test.

## API Documentation

All API endpoints require the `X-Tenant-ID` header for multi-tenant isolation.

Base URL: `http://localhost/api/v1`

### 1. Create Notification

**Endpoint**: `POST /api/v1/notifications`

**Description**: Creates a notification and queues it for processing.

**Headers**:
```
X-Tenant-ID: tenant-123
Content-Type: application/json
```

**Request Body**:
```json
{
  "user_id": 1,
  "channel": "log",
  "title": "Welcome Notification",
  "body": "Welcome to our platform!",
  "payload": {
    "custom_field": "value"
  },
  "scheduled_at": "2025-02-11T10:00:00Z",
  "max_attempts": 5
}
```

**Required Fields**:
- `user_id` (integer): Must exist in users table
- `channel` (string): One of `log`, `email`, `sms`
- `title` (string, max 255 characters)
- `body` (string)

**Optional Fields**:
- `payload` (array): Additional JSON data
- `scheduled_at` (datetime): Future date for scheduled delivery
- `max_attempts` (integer, 1-10): Maximum retry attempts

**Response**: `202 Accepted`

```json
{
  "data": {
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "status": "pending",
    "channel": "log",
    "user_id": 1,
    "title": "Welcome Notification",
    "created_at": "2025-02-10T12:00:00+00:00",
    "scheduled_at": "2025-02-11T10:00:00+00:00"
  }
}
```

**Error Responses**:
- `400 Bad Request`: Missing `X-Tenant-ID` header
- `422 Unprocessable Entity`: Validation errors
- `500 Internal Server Error`: Rate limit exceeded


### 2. Get Recent Notifications

**Endpoint**: `GET /api/v1/notifications/recent`

**Description**: Retrieves recent notifications with optional filtering and pagination.

**Headers**:
```
X-Tenant-ID: tenant-123
```

**Query Parameters** (all optional):
- `limit` (integer, 1-100): Items per page (default: 20)
- `page` (integer, min: 1): Page number (default: 1)
- `user_id` (integer): Filter by user ID
- `channel` (string): Filter by channel (log, email, sms)
- `status` (string): Filter by status (pending, processing, sent, failed, cancelled)

**Response**: `200 OK`

```json
{
  "data": [
    {
      "uuid": "550e8400-e29b-41d4-a716-446655440000",
      "status": "sent",
      "channel": "log",
      "user_id": 1,
      "title": "Welcome Notification",
      "created_at": "2025-02-10T12:00:00+00:00",
      "scheduled_at": null
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 1
  }
}
```

### 3. Get Summary Statistics

**Endpoint**: `GET /api/v1/notifications/summary`

**Description**: Returns summary statistics of notifications by status and optionally by channel.

**Headers**:
```
X-Tenant-ID: tenant-123
```

**Query Parameters** (all optional):
- `since` (date): Filter notifications created after this date (ISO 8601 format)
- `by_channel` (boolean): Include channel breakdown (true/false)

**Response**: `200 OK`

**Without channel breakdown**:
```json
{
  "data": {
    "counts_by_status": {
      "pending": 5,
      "processing": 2,
      "sent": 100,
      "failed": 3,
      "cancelled": 0
    },
    "total": 110
  }
}
```

**With channel breakdown** (`by_channel=true`):
```json
{
  "data": {
    "counts_by_status": {
      "pending": 5,
      "processing": 2,
      "sent": 100,
      "failed": 3,
      "cancelled": 0
    },
    "total": 110,
    "by_channel": {
      "log": {
        "pending": 3,
        "processing": 1,
        "sent": 50,
        "failed": 1,
        "cancelled": 0
      },
      "email": {
        "pending": 2,
        "processing": 1,
        "sent": 40,
        "failed": 2,
        "cancelled": 0
      },
      "sms": {
        "pending": 0,
        "processing": 0,
        "sent": 10,
        "failed": 0,
        "cancelled": 0
      }
    }
  }
}
```

## Design Decisions

### Architecture

The system follows a **layered architecture** pattern:

```
Controller → Service → Repository → Model
```

- **Controllers**: Handle HTTP requests, validation, and response formatting
- **Services**: Contain business logic (NotificationService, NotificationProcessor, etc.)
- **Repositories**: Abstract data access layer (NotificationRepository)
- **Models**: Eloquent models representing database entities

### Design Patterns

#### 1. DTO Pattern (Data Transfer Objects)
- `AutoMappedDto`: Base class using reflection for automatic request-to-DTO mapping
- `CreateNotificationDto`, `GetRecentNotificationsDto`, `GetSummaryNotificationsDto`
- Benefits: Type safety, validation, and separation of concerns

#### 2. Repository Pattern
- `BaseRepository`: Abstract base with common CRUD operations
- `NotificationRepository`: Notification-specific queries with caching
- Contracts: `BaseRepositoryContract`, `NotificationRepositoryContract` for dependency inversion
- Benefits: Testability, abstraction, and centralized data access logic

#### 3. Factory Pattern
- `NotificationChannelFactory`: Creates channel instances based on configuration
- Channels defined in `config/notification_channels.php`
- Benefits: Easy extensibility - add new channels without modifying existing code

#### 4. Action Pattern
- `GetRecentNotificationsAction`, `GetSummaryNotificationsAction`
- Encapsulates business logic for specific operations
- Benefits: Single responsibility, testability, reusability

### Multi-Tenancy

**Implementation**: Header-based tenant resolution using `X-Tenant-ID` header.

- **Middleware**: `SetTenantContext` middleware extracts tenant ID from header
- **Context API**: Uses Laravel's Context API for request-scoped tenant storage
- **Global Scope**: `Notification` model has a global scope that automatically filters by `tenant_id`
- **Helper Function**: `getTenantId()` provides global access to tenant context

**Important Note**: In a production system, tenants would typically be stored in a separate `tenants` table with proper relationships, validation, and management. For this assessment, tenant IDs are passed via header without a tenants table.

### Queue System

**Implementation**: Laravel Redis queue driver using Redis Lists (not Pub/Sub).

- **Why Redis Lists**: Provides persistence, retry mechanisms, and reliable job processing
- **Job**: `SendNotificationJob` handles notification processing
- **Retry Logic**: Exponential backoff (3^n seconds, capped at 600 seconds)
- **Modular Design**: Separated into `NotificationProcessor`, `NotificationStateManager`, `NotificationDeliveryService`

### Caching Strategy

**Tag-Based Caching**: Uses Laravel's tag-based cache for efficient invalidation.

- **Recent Notifications**: 120 seconds TTL
- **Summary Statistics**: 300 seconds TTL
- **Automatic Invalidation**: Cache flushed on notification write operations
- **Tenant-Scoped**: Cache keys include tenant ID for isolation

### Rate Limiting

**Per-Tenant, Per-User**: Rate limiting is scoped by both tenant and user.

- **Key Format**: `notifications:{tenant_id}:{user_id}`
- **Default**: 10 notifications per user per hour
- **Configurable**: Via environment variables
- **Implementation**: Uses Laravel's `RateLimiter` facade

### Error Handling

- **Comprehensive Logging**: All operations logged with appropriate levels (info, error)
- **Error Storage**: Failed notifications store error messages in `last_error` field
- **Exponential Backoff**: Failed jobs retry with increasing delays
- **Terminal States**: Notifications in terminal states (sent, failed, cancelled) are not reprocessed

### Job Processing Flow

1. **NotificationProcessor**: Orchestrates the processing flow
2. **Schedule Check**: Validates if notification is scheduled for future
3. **Distributed Lock**: Prevents concurrent processing
4. **State Management**: `NotificationStateManager` handles status transitions
5. **Delivery**: `NotificationDeliveryService` handles channel-specific delivery
6. **Error Handling**: Records errors and checks exhaustion before retry

## Assumptions

The following assumptions were made during development:

1. **Multi-Tenant System**: 
   - Tenant ID is always provided via `X-Tenant-ID` header
   - **Important**: In a real-world scenario, there would be a separate `tenants` table to store tenant data with proper relationships, validation, and management. For this assessment, tenants are simply passed through the header without a tenants table.

2. **Queue Workers**: Notifications won't be processed without active queue workers running

3. **Redis Availability**: Queue and cache require Redis to be running and accessible

4. **Users Exist**: The `user_id` field must reference existing users in the database

5. **Channels Configured**: Available notification channels are defined in `config/notification_channels.php` (currently: log, email, sms)

6. **Rate Limiting**: Configurable via environment variables with sensible defaults (10/hour)

7. **Scheduled Notifications**: Future-dated `scheduled_at` values are supported and jobs will be released until the scheduled time

8. **Docker Environment**: All commands (composer, artisan) should be run from inside Docker container or using Laravel Sail

9. **Notification Channels**: Email and SMS channels log messages but don't perform actual delivery (as per requirement to simulate)
