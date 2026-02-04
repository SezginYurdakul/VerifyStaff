# VerifyStaff

Offline-first attendance tracking system built with Laravel 11 and React PWA.

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
  - Token-based authentication (Laravel Sanctum)

- **Comprehensive Reporting**
  - Daily/Weekly/Monthly/Yearly summaries
  - Late arrivals and early departures tracking
  - Overtime calculations
  - Anomaly flagging system

## Tech Stack

| Component | Technology |
|-----------|------------|
| Backend | Laravel 11 (PHP 8.3+) |
| Frontend | React (Vite PWA) |
| Database | MySQL 8.0 |
| Offline Storage | IndexedDB (Dexie.js) |
| Authentication | Laravel Sanctum |
| Security | TOTP Algorithm |

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
| DELETE | `/api/v1/users/{id}` | Delete user |
| POST | `/api/v1/users/{id}/resend-invite` | Resend invitation |

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
| GET | `/api/v1/attendance/status` | Get attendance status |

### TOTP
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/totp/generate` | Generate TOTP code |
| POST | `/api/v1/totp/verify` | Verify TOTP code |

### Reports
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/reports/summary/{id}/daily` | Daily summary |
| GET | `/api/v1/reports/summary/{id}/weekly` | Weekly summary |
| GET | `/api/v1/reports/summary/{id}/monthly` | Monthly summary |
| GET | `/api/v1/reports/summary/{id}/yearly` | Yearly summary |
| GET | `/api/v1/reports/all/{period}` | All workers summary |
| GET | `/api/v1/reports/flagged` | Flagged logs |

### Settings (Admin)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/settings` | List settings |
| PUT | `/api/v1/settings/{key}` | Update setting |
| GET | `/api/v1/settings/work-hours` | Get work hours config |
| PUT | `/api/v1/settings/config/attendance-mode` | Change attendance mode |
| PUT | `/api/v1/settings/config/shifts` | Update shift definitions |
| PUT | `/api/v1/settings/config/working-days` | Update working days |

### Kiosks (Admin)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/kiosks` | List kiosks |
| POST | `/api/v1/kiosks` | Create kiosk |
| GET | `/api/v1/kiosk/{code}/code` | Get kiosk QR code |

## Testing

The project includes comprehensive test coverage with 258 tests.

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

Tests run in ~1.5 seconds using optimized configuration:
- In-memory SQLite database
- Reduced bcrypt rounds
- Array drivers for cache/session/queue

See [docs/TESTING.md](docs/TESTING.md) for testing strategy documentation.
See [docs/HIGH_PERFORMANCE_TESTING.md](docs/HIGH_PERFORMANCE_TESTING.md) for performance optimization guide.

## Project Structure

```
app/
├── Console/Commands/      # Artisan commands
├── Events/                # Application events
├── Exceptions/            # Custom exceptions
├── Http/
│   ├── Controllers/Api/V1/  # API controllers
│   ├── Requests/Api/        # Form requests
│   └── Resources/           # API resources
├── Jobs/                  # Queue jobs
├── Listeners/             # Event listeners
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

### Environment Variables

```env
# Application
APP_ENV=production
APP_DEBUG=false

# Database
DB_CONNECTION=mysql
DB_DATABASE=verifystaff

# Security
BCRYPT_ROUNDS=12

# Services
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

## User Roles

| Role | Permissions |
|------|-------------|
| **Admin** | Full access to all features, settings, and reports |
| **Representative** | Scan workers, sync logs, view assigned reports |
| **Worker** | Generate TOTP codes, self check-in (kiosk mode) |

## License

This project is proprietary software.

## Documentation

- [Testing Strategy](docs/TESTING.md)
- [High Performance Testing](docs/HIGH_PERFORMANCE_TESTING.md)
- [Project Plan](ProjectPlan.md)
