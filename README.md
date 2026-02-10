# VerifyStaff

Offline-first attendance tracking system built with Laravel 12 and React PWA.

## Overview

VerifyStaff is a lightweight, high-reliability attendance tracking solution designed for environments with unstable or no internet connection. It eliminates the need for expensive biometric hardware by using a secure, peer-to-peer QR scanning model with TOTP (Time-based One-Time Password) technology.

## Features

- **Dual Attendance Modes**
  - **Representative Mode**: Workers show QR codes, representatives scan them
  - **Kiosk Mode**: Fixed kiosks display QR codes, workers scan them

- **Offline-First Design**
  - Works without internet connection
  - Automatic sync when connection is restored
  - Local validation using cached staff data

- **Security**
  - TOTP-based QR codes (30-second validity)
  - SHA256-based event deduplication
  - Authentication via Laravel Sanctum Personal Access Tokens (Bearer tokens, non-JWT)

- **Comprehensive Reporting**
  - Daily/Weekly/Monthly/Yearly summaries
  - Late arrivals and early departures tracking
  - Overtime calculations
  - Anomaly flagging system

- **Invite-Based Onboarding**
  - Admin creates user, invite email is queued automatically
  - Worker sets password via invite link
  - Queued mail delivery (database queue driver)

## Tech Stack

| Component | Technology |
|-----------|------------|
| Backend | Laravel 12 (PHP 8.3+) |
| Frontend | React (Vite PWA) |
| Database | MySQL 8.0 |
| Offline Storage | IndexedDB (Dexie.js) |
| Authentication | Laravel Sanctum |
| Security | TOTP Algorithm |
| Queue | Database driver |

## Requirements

- PHP 8.3+
- Composer
- MySQL 8.0+
- Node.js 20+ (for frontend)
- Docker & Docker Compose (optional)

## Installation

### Using Docker (Recommended)

```bash
# Clone the repository
git clone https://github.com/yourusername/verifystaff.git
cd verifystaff

# Copy environment file
cp .env.example .env

# Start containers
docker-compose up -d

# Install dependencies
docker-compose exec app composer install

# Generate application key
docker-compose exec app php artisan key:generate

# Run migrations
docker-compose exec app php artisan migrate

# Access the application
# API: http://localhost:8000
# Frontend (dev): http://localhost:5174
```

### Manual Installation

```bash
# Clone the repository
git clone https://github.com/yourusername/verifystaff.git
cd verifystaff

# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Configure your database in .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=verifystaff
# DB_USERNAME=your_username
# DB_PASSWORD=your_password

# Generate application key
php artisan key:generate

# Run migrations (includes default settings)
php artisan migrate

# Start the development server
php artisan serve
```

## API Endpoints

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/auth/login` | Login |
| POST | `/api/v1/auth/logout` | Logout |
| GET | `/api/v1/auth/me` | Get current user |
| POST | `/api/v1/auth/refresh` | Refresh token |

### User Invitations
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/invite/validate` | Validate invite token |
| POST | `/api/v1/invite/accept` | Accept invite and set password |

### Users (Admin)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/users` | List all users |
| POST | `/api/v1/users` | Create user and send invite |
| GET | `/api/v1/users/{id}` | Get user details |
| PUT | `/api/v1/users/{id}` | Update user |
| DELETE | `/api/v1/users/{id}` | Soft delete user |
| POST | `/api/v1/users/{id}/resend-invite` | Resend invitation |
| POST | `/api/v1/users/{id}/restore` | Restore soft-deleted user |
| DELETE | `/api/v1/users/{id}/force` | Permanently delete user |

### Departments (Admin)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/departments` | List departments |
| POST | `/api/v1/departments` | Create department |
| GET | `/api/v1/departments/{id}` | Get department details |
| PUT | `/api/v1/departments/{id}` | Update department |
| DELETE | `/api/v1/departments/{id}` | Delete department |

### Sync (Representative Mode)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/sync/staff` | Get staff list |
| POST | `/api/v1/sync/logs` | Upload attendance logs |
| GET | `/api/v1/time` | Get server time |

### Attendance (Kiosk Mode)
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/attendance/self-check` | Self check-in/out |
| POST | `/api/v1/attendance/sync-offline` | Sync offline kiosk logs |
| GET | `/api/v1/attendance/status` | Get attendance status |

### TOTP
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/totp/generate` | Generate TOTP code |
| POST | `/api/v1/totp/verify` | Verify TOTP code |

### Dashboard
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/dashboard/overview` | Dashboard overview stats |
| GET | `/api/v1/dashboard/trends` | Attendance trends |
| GET | `/api/v1/dashboard/anomalies` | Detected anomalies |

### Reports
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/reports/summary/{id}/daily` | Daily summary |
| GET | `/api/v1/reports/summary/{id}/weekly` | Weekly summary |
| GET | `/api/v1/reports/summary/{id}/monthly` | Monthly summary |
| GET | `/api/v1/reports/summary/{id}/yearly` | Yearly summary |
| GET | `/api/v1/reports/logs/{id}` | Worker attendance logs |
| GET | `/api/v1/reports/all/daily` | All workers daily summary |
| GET | `/api/v1/reports/all/weekly` | All workers weekly summary |
| GET | `/api/v1/reports/all/monthly` | All workers monthly summary |
| GET | `/api/v1/reports/all/yearly` | All workers yearly summary |
| GET | `/api/v1/reports/flagged` | Flagged logs |

### Settings
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/settings` | List all settings (Admin) |
| GET | `/api/v1/settings/group/{group}` | Get settings by group (Admin) |
| GET | `/api/v1/settings/{key}` | Get single setting (Admin) |
| PUT | `/api/v1/settings/{key}` | Update setting (Admin) |
| PUT | `/api/v1/settings` | Bulk update settings (Admin) |
| GET | `/api/v1/settings/work-hours` | Get work hours config |
| GET | `/api/v1/settings/attendance-mode` | Get attendance mode |
| PUT | `/api/v1/settings/config/attendance-mode` | Change attendance mode (Admin) |
| PUT | `/api/v1/settings/config/shifts` | Update shift definitions (Admin) |
| PUT | `/api/v1/settings/config/working-days` | Update working days (Admin) |

### Kiosks (Admin)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/kiosks` | List kiosks |
| POST | `/api/v1/kiosks` | Create kiosk |
| GET | `/api/v1/kiosks/{code}` | Get kiosk details |
| PUT | `/api/v1/kiosks/{code}` | Update kiosk |
| POST | `/api/v1/kiosks/{code}/regenerate-token` | Regenerate kiosk token |
| GET | `/api/v1/kiosk/{code}/code` | Get kiosk QR code (public) |

## Testing

The project includes comprehensive test coverage with 414 tests.

```bash
# Run all tests
php artisan test

# Run unit tests only
php artisan test --testsuite=Unit

# Run feature tests only
php artisan test --testsuite=Feature

# Run with coverage
php artisan test --coverage
```

### Test Performance

Tests run in ~3 seconds using optimized configuration:
- In-memory SQLite database
- Reduced bcrypt rounds
- Array drivers for cache/session/queue

## Project Structure

```
app/
├── Console/Commands/      # Artisan commands
├── Events/                # Application events
├── Exceptions/            # Custom exceptions
├── Http/
│   ├── Controllers/Api/V1/  # API controllers
│   ├── Requests/Api/        # Form request validation
│   └── Resources/           # API resources
├── Jobs/                  # Queue jobs
├── Listeners/             # Event listeners
├── Mail/                  # Mailable classes
├── Models/                # Eloquent models
├── Observers/             # Model observers
├── Providers/             # Service providers
└── Services/              # Business logic services

database/
├── factories/             # Model factories
├── migrations/            # Database migrations
└── seeders/               # Database seeders

tests/
├── Feature/Api/           # API feature tests
└── Unit/                  # Unit tests
    ├── Events/
    ├── Exceptions/
    ├── Models/
    ├── Requests/
    ├── Resources/
    └── Services/
```

## Configuration

### Default Settings (seeded via migration)

| Setting | Default | Description |
|---------|---------|-------------|
| work_start_time | 09:00 | Work day start |
| work_end_time | 18:00 | Work day end |
| break_duration_minutes | 60 | Break duration |
| late_threshold_minutes | 15 | Grace period for late arrival |
| early_departure_threshold_minutes | 15 | Grace period for early departure |
| attendance_mode | representative | Default attendance mode |
| shifts_enabled | false | Enable multiple shift support |
| timezone | Europe/Istanbul | System timezone |

### Environment Variables (Defaults)

```env
# Application
APP_ENV=production
APP_DEBUG=false

# URLs
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5174

# Database
DB_CONNECTION=mysql
DB_DATABASE=verifystaff

# Services (default drivers)
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

# Logging
LOG_LEVEL=warning

# Mail (use "log" for development)
MAIL_MAILER=log
```

### Mail (SMTP)

For production, configure SMTP in `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=YOUR_EMAIL
MAIL_PASSWORD=YOUR_APP_PASSWORD
MAIL_ENCRYPTION=tls
```

### Production Notes

- Set `APP_ENV=production` and `APP_DEBUG=false`
- Never commit `.env` files or real credentials to the repository
- Rotate credentials immediately if a secret is ever exposed
- Process queued jobs: `php artisan queue:work`

## User Roles

| Role | Permissions |
|------|-------------|
| **Admin** | Full access to all features, settings, and reports |
| **Representative** | Scan workers, sync logs, view assigned reports |
| **Worker** | Generate TOTP codes, self check-in (kiosk mode) |

## License

This project is shared publicly for demonstration purposes.
All rights reserved.

## Documentation

- [Error Handling](docs/ERROR_HANDLING.md)
- [Usage Guide](docs/USAGE_GUIDE.md)
- [Project Plan](ProjectPlan.md)
