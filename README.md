# Uptime Monitor API

A Laravel 13 API that monitors site URLs for uptime, stores check history, and sends email notifications when a site goes down or recovers.

---

## Tech Stack

| Layer | Choice | Reason |
|---|---|---|
| Framework | Laravel 13 / PHP 8.4 | Requirement |
| Queue | Database queue (default) / Redis (production) | Zero-setup locally, swap for prod |
| HTTP Client | Laravel `Http` (Guzzle wrapper) | Built-in, clean API, no extra dependency |
| Scheduler | Laravel Console Kernel | Manages per-monitor `check_interval` via `everyMinute()` |
| Notifications | Laravel Mail via anonymous notifiable | No `User` model required |

---

## Setup

### 1. Clone & install dependencies

```bash
git clone https://github.com/YOUR_USERNAME/uptime-monitor.git
cd uptime-monitor
composer install
```

### 2. Environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and set:

```env
# Notification email (required for alerts)
UPTIME_NOTIFY_EMAIL=you@example.com

# Mail driver — use "log" for local dev (emails go to storage/logs/laravel.log)
MAIL_MAILER=log

# Queue — "database" works out of the box; use "redis" for production
QUEUE_CONNECTION=database
```

### 3. Database

**SQLite (quickest for local dev):**
```bash
touch database/database.sqlite
# DB_CONNECTION=sqlite is already the default in .env.example
php artisan migrate
```

**MySQL / PostgreSQL:**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=uptime_monitor
DB_USERNAME=root
DB_PASSWORD=secret
```
```bash
php artisan migrate
```

### 4. Queue worker

Checks are dispatched as queued jobs. You **must** run the worker:

```bash
php artisan queue:work
```

For production, use a process manager (Supervisor, systemd) to keep this running.

### 5. Scheduler

The scheduler dispatches check jobs every minute, respecting each monitor's `check_interval`.

**Local development:**
```bash
php artisan schedule:work
```

**Production (cron):**
```bash
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

### 6. Serve

```bash
php artisan serve
# API available at http://localhost:8000/api
```

---

## API Reference

### POST `/api/monitors`
Register a new URL to monitor.

**Request body:**
```json
{
  "url": "https://example.com",
  "check_interval": 5,
  "threshold": 3
}
```

| Field | Type | Required | Description |
|---|---|---|---|
| `url` | string | ✅ | Valid, unique HTTP/HTTPS URL |
| `check_interval` | integer | ❌ | Minutes between checks (default: 5, min: 1, max: 60) |
| `threshold` | integer | ❌ | Consecutive failures before marking as down (default: 3, min: 1) |

**Response `201 Created`:**
```json
{
  "data": {
    "id": 1,
    "url": "https://example.com",
    "check_interval": 5,
    "threshold": 3,
    "status": "pending",
    "last_checked_at": null,
    "uptime_percentage": null,
    "created_at": "2026-05-13T10:00:00.000000Z"
  }
}
```

**Validation error `422`:**
```json
{
  "message": "The url field is required.",
  "errors": {
    "url": ["The url field is required."]
  }
}
```

---

### GET `/api/monitors`
List all monitors with current status.

**Response `200 OK`:**
```json
{
  "data": [
    {
      "id": 1,
      "url": "https://example.com",
      "check_interval": 5,
      "threshold": 3,
      "status": "up",
      "last_checked_at": "2026-05-13T10:05:00.000000Z",
      "uptime_percentage": 99.5,
      "created_at": "2026-05-13T10:00:00.000000Z"
    }
  ]
}
```

Status values: `pending` | `up` | `down`

---

### GET `/api/monitors/{id}/history`
Paginated check history for a monitor, ordered newest first.

**Query parameters:**

| Param | Default | Max |
|---|---|---|
| `page` | 1 | — |
| `per_page` | 15 | 100 |

**Response `200 OK`:**
```json
{
  "data": [
    {
      "id": 1,
      "monitor_id": 1,
      "status_code": 200,
      "response_time_ms": 245,
      "is_up": true,
      "checked_at": "2026-05-13T10:05:00.000000Z"
    },
    {
      "id": 2,
      "monitor_id": 1,
      "status_code": 0,
      "response_time_ms": null,
      "is_up": false,
      "checked_at": "2026-05-13T10:10:00.000000Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 50
  }
}
```

- `is_up` is `true` for 2xx and 3xx status codes
- `status_code` is `0` and `response_time_ms` is `null` on timeout or connection failure
- **Not found `404`:** `{ "message": "Monitor not found." }`

---

## How It Works

### Check cycle

1. A monitor is registered via `POST /api/monitors` — status starts as `pending`
2. `CheckMonitorJob` is immediately dispatched for the first check
3. The Laravel Scheduler fires every minute and dispatches `CheckMonitorJob` for each monitor whose `check_interval` divides the current minute
4. Each job hits the URL, records the result in `monitor_checks`, and updates the monitor's status

### Threshold logic

A monitor is only marked `down` after **N consecutive failures** (where N = `threshold`). A single blip won't trigger an alert. Once the site recovers, the status flips back to `up` immediately on the next successful check.

### Notifications

Email alerts are sent (to `UPTIME_NOTIFY_EMAIL`) only on **status transitions**:
- `up` → `down`: site went down
- `down` → `up`: site recovered

The `pending` state never triggers a notification — it's only used before the first check completes.

---

## Running Tests

```bash
php artisan test
# or
./vendor/bin/phpunit
```

Tests use an in-memory SQLite database and a fake queue so no real HTTP calls or emails are made.

---

## Design Decisions

**Why `Http::timeout(10)` and not raw curl?**
Laravel's HTTP client is a clean Guzzle wrapper with built-in retry support, exception normalisation, and testability via `Http::fake()`. No reason to drop to raw curl.

**Why anonymous notifiable instead of a User model?**
The assessment has no auth requirement and no users table. `Notification::route('mail', $email)` keeps things simple and avoids coupling to a user model.

**Why the Kernel scheduler instead of self-scheduling jobs only?**
The Kernel (`everyMinute()`) is the authoritative driver. It guarantees checks still run even after a queue worker restart or a failed job — nothing gets "lost" because a job forgot to re-dispatch itself. Both mechanisms coexist: the scheduler dispatches, the job does the work.

**Why `cascadeOnDelete` on `monitor_checks`?**
Deleting a monitor should clean up all its history automatically. This is handled at the DB level, not in application code, so it works even if records are deleted outside Laravel.
# uptime-monitoring
