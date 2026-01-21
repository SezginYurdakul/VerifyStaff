# Testing Documentation

This document describes the testing strategy, structure, and guidelines for the VerifyStaff attendance management system.

## Table of Contents

- [Overview](#overview)
- [Testing Philosophy](#testing-philosophy)
- [Test Structure](#test-structure)
- [Unit Tests](#unit-tests)
- [Feature Tests](#feature-tests)
- [Running Tests](#running-tests)
- [Test Coverage Summary](#test-coverage-summary)
- [Writing New Tests](#writing-new-tests)

## Overview

The VerifyStaff application uses PHPUnit as its testing framework, integrated with Laravel's testing utilities. The test suite is divided into two main categories:

- **Unit Tests**: Test individual components in isolation
- **Feature Tests**: Test complete API endpoints and workflows

**Total Test Count**: 258 tests with 744 assertions

## Testing Philosophy

### Why Unit Tests?

Unit tests are used for components that:

1. **Have no external dependencies** - Pure functions, data transformers, utility classes
2. **Can be tested in isolation** - No database, no HTTP requests, no file system
3. **Are deterministic** - Same input always produces same output
4. **Are fast to execute** - No I/O operations

Examples in our codebase:
- `TotpService` - Cryptographic operations that are deterministic
- Model attribute accessors and mutators
- Form Request validation rules
- Event data structures
- Exception factory methods
- Resource transformations (with mocked models)

### Why Feature Tests?

Feature tests are used for:

1. **API endpoint testing** - Full HTTP request/response cycle
2. **Database interactions** - Creating, reading, updating, deleting records
3. **Authentication/Authorization** - Testing middleware and guards
4. **Integration scenarios** - Multiple components working together
5. **Business logic validation** - Complete workflows

Examples in our codebase:
- Authentication flow (login, register, logout)
- TOTP verification with database lookups
- Attendance recording with kiosk validation
- Report generation with aggregated data

### Decision Matrix

| Component Type | Unit Test | Feature Test | Reason |
|----------------|-----------|--------------|--------|
| Service (no DB) | ✅ | ❌ | Pure logic, no dependencies |
| Service (with DB) | ❌ | ✅ | Requires database state |
| Model attributes | ✅ | ❌ | Simple property access |
| Model relationships | ❌ | ✅ | Requires database |
| Model scopes | ❌ | ✅ | Requires database queries |
| Form Requests | ✅ | ❌ | Rules can be tested as arrays |
| Resources | ✅ | ❌ | Can mock model instances |
| Events | ✅ | ❌ | Simple data containers |
| Exceptions | ✅ | ❌ | Factory methods, no deps |
| Controllers | ❌ | ✅ | Full HTTP cycle needed |
| Middleware | ❌ | ✅ | Request/response cycle |

### Important Clarifications

#### Mocking Strategy

We follow the principle of **only mocking what we own**:

- ✅ **Mock our own services** when testing consumers
- ✅ **Mock Log facade** when testing audit logging (we care about "was it called?")
- ❌ **Don't mock Laravel core classes** like `Request`, `Collection`, `Carbon`
- ❌ **Don't mock Eloquent models** - use real instances with fake data

```php
// Good: Real model instance with test data
$user = new User(['name' => 'Test', 'email' => 'test@example.com']);
$resource = new UserResource($user);

// Avoid: Mocking the model
$user = Mockery::mock(User::class);
$user->shouldReceive('getAttribute')->with('name')->andReturn('Test');
```

#### Form Request Testing Strategy

Form Request unit tests verify **rule configuration**, not **validation execution**:

```php
// Unit Test: Check that the rule EXISTS (no database needed)
public function test_rules_has_worker_id_field(): void
{
    $rules = $this->request->rules();
    $this->assertContains('exists:users,id', $rules['worker_id']);
}

// Feature Test: Check that validation WORKS (database needed)
public function test_verify_fails_for_nonexistent_worker(): void
{
    $response = $this->postJson('/api/v1/totp/verify', [
        'worker_id' => 99999,  // Does not exist in DB
        'code' => '123456',
    ]);
    $response->assertStatus(422);
}
```

This separation allows unit tests to remain fast and database-free while ensuring validation behavior is still covered in feature tests.

#### Model Scope and Relationship Testing

Model relationships and scopes require database queries, so they are tested in Feature tests:

```php
// Feature Test: Relationship tested through API
public function test_worker_logs_returns_attendance_data(): void
{
    $worker = User::factory()->create(['role' => 'worker']);
    AttendanceLog::factory()->count(5)->create(['worker_id' => $worker->id]);

    $response = $this->getJson("/api/v1/reports/logs/{$worker->id}");

    $response->assertOk()
        ->assertJsonCount(5, 'days.0.logs');
}
```

For larger projects, consider creating a separate `tests/Integration/` directory for database-only tests that don't need full HTTP cycle.

## Test Structure

```
tests/
├── Unit/
│   ├── Events/
│   │   ├── SettingChangedTest.php
│   │   └── TotpVerifiedTest.php
│   ├── Exceptions/
│   │   └── ApiExceptionTest.php
│   ├── Models/
│   │   ├── AttendanceLogTest.php
│   │   ├── KioskTest.php
│   │   ├── SettingTest.php
│   │   ├── UserTest.php
│   │   └── WorkSummaryTest.php
│   ├── Requests/
│   │   ├── LoginRequestTest.php
│   │   ├── RegisterRequestTest.php
│   │   ├── SelfCheckRequestTest.php
│   │   └── VerifyTotpRequestTest.php
│   ├── Resources/
│   │   ├── UserResourceTest.php
│   │   └── WorkerResourceTest.php
│   └── Services/
│       ├── AuditLoggerTest.php
│       └── TotpServiceTest.php
├── Feature/
│   └── Api/
│       ├── AttendanceTest.php
│       ├── AuthTest.php
│       ├── KioskTest.php
│       ├── ReportsTest.php
│       ├── SettingsTest.php
│       ├── SyncTest.php
│       └── TotpTest.php
└── TestCase.php
```

## Unit Tests

### Services

#### TotpServiceTest (14 tests)

Tests the TOTP (Time-based One-Time Password) service which generates and verifies 6-digit codes.

| Test | Description |
|------|-------------|
| `test_generate_code_returns_six_digit_code` | Validates code format |
| `test_generate_code_returns_valid_remaining_seconds` | Checks time window (0-30s) |
| `test_same_token_generates_same_code_within_time_window` | Deterministic behavior |
| `test_different_tokens_generate_different_codes` | Uniqueness per token |
| `test_verify_code_returns_true_for_valid_code` | Successful verification |
| `test_verify_code_returns_false_for_invalid_code` | Rejection of invalid codes |
| `test_verify_code_returns_false_for_wrong_token` | Token-specific verification |
| `test_generate_qr_content_returns_base64_encoded_json` | QR data format |
| `test_parse_qr_content_*` | Various parsing scenarios |
| `test_code_generation_is_deterministic` | Reproducible results |
| `test_verify_accepts_codes_within_window` | Time tolerance |

#### AuditLoggerTest (12 tests)

Tests the audit logging service using mocked Log facade.

```php
protected function setUp(): void
{
    parent::setUp();
    $this->logMock = Mockery::mock(LoggerInterface::class);
    $this->logMock->shouldReceive('info')->andReturnNull();
    $this->logMock->shouldReceive('warning')->andReturnNull();
    Log::shouldReceive('channel')->with('daily')->andReturn($this->logMock);
}
```

| Test | Description |
|------|-------------|
| `test_attendance_logs_to_daily_channel` | Attendance event logging |
| `test_totp_verification_success_logs_to_daily_channel` | TOTP success logging |
| `test_totp_verification_failure_logs_to_daily_channel` | TOTP failure logging |
| `test_settings_change_logs_to_daily_channel` | Settings change logging |
| `test_auth_success_logs_to_daily_channel` | Auth success logging |
| `test_auth_failure_logs_to_daily_channel` | Auth failure logging |
| `test_security_logs_to_daily_channel` | Security event logging |
| `test_work_summary_logs_to_daily_channel` | Work summary logging |
| `test_sync_logs_to_daily_channel` | Sync operation logging |

### Models

Model unit tests focus on:
- Static methods (e.g., `generateEventId`, `generateSecretToken`)
- Attribute accessors (e.g., `total_hours`, `formatted_total_time`)
- Configuration arrays (`$fillable`, `$hidden`, `$casts`)
- Simple boolean methods (e.g., `isActive`, `isAdmin`)

**Why not test relationships in unit tests?**

Relationships require database queries. Testing `$user->attendanceLogs()` would need actual records in the database, making it a feature test concern.

### Events

Events are simple data containers. Unit tests verify:
- Constructor properly stores all parameters
- Properties are accessible
- Different data types are handled correctly

```php
public function test_event_stores_key(): void
{
    $event = new SettingChanged('test_key', 'old', 'new', null);
    $this->assertEquals('test_key', $event->key);
}
```

### Form Requests

Form Request unit tests verify:
- `authorize()` returns expected value
- `rules()` contains expected validation rules
- Custom messages are defined

```php
public function test_rules_has_email_field(): void
{
    $request = new RegisterRequest();
    $rules = $request->rules();

    $this->assertArrayHasKey('email', $rules);
    $this->assertStringContainsString('email', $rules['email']);
}
```

**Note**: We don't test actual validation behavior in unit tests. That's covered by feature tests which send real HTTP requests.

### Resources

Resource unit tests verify JSON transformation logic:

```php
public function test_resource_does_not_expose_password(): void
{
    $user = new User(['password' => 'secret']);
    $resource = new UserResource($user);
    $array = $resource->toArray(request());

    $this->assertArrayNotHasKey('password', $array);
}
```

### Exceptions

Exception unit tests verify factory methods and default values:

```php
public function test_not_found_factory(): void
{
    $exception = ApiException::notFound('User');

    $this->assertEquals('User not found.', $exception->getMessage());
    $this->assertEquals(404, $exception->getStatusCode());
    $this->assertEquals('NOT_FOUND', $exception->getErrorCode());
}
```

## Feature Tests

Feature tests use Laravel's `RefreshDatabase` trait to ensure a clean database state for each test.

### AuthTest (19 tests)

Tests the complete authentication flow:

| Category | Tests |
|----------|-------|
| Register | Valid registration, duplicate email/phone/employee_id, missing fields |
| Login | Email/phone/employee_id login, wrong password, inactive user |
| Logout | Successful logout, token invalidation |
| Me | Profile retrieval, password not exposed |
| Refresh | Token refresh, old token deletion |

### TotpTest (16 tests)

Tests TOTP generation and verification through API:

| Category | Tests |
|----------|-------|
| Generate | Worker can generate, admin/rep cannot, missing token |
| Verify | Valid code, invalid code, inactive worker, missing token |
| Validation | Required fields, code format |

### AttendanceTest (14 tests)

Tests self-service attendance (kiosk mode):

| Category | Tests |
|----------|-------|
| Self Check | Check-in, check-out, toggle behavior |
| Mode Validation | Kiosk mode required, invalid kiosk, inactive kiosk |
| TOTP Validation | Invalid TOTP rejection |
| Status | Current status, checked-in/out states |

### SyncTest (16 tests)

Tests representative-based attendance sync:

| Category | Tests |
|----------|-------|
| Server Time | Public endpoint access |
| Staff List | Rep/admin access, worker exclusion, secret tokens |
| Log Sync | Successful sync, duplicates, work minutes calculation |
| Validation | Kiosk mode blocking, future timestamps flagging |
| Toggle Mode | Auto-detection of check-in/out type |

### SettingsTest (21 tests)

Tests system configuration management:

| Category | Tests |
|----------|-------|
| Index | Admin access, rep/worker denied |
| Group | Valid groups, invalid group error |
| Show | Single setting retrieval |
| Update | Value update, type validation, event dispatch |
| Bulk Update | Multiple settings, partial success |
| Work Hours | Rep/admin access for work config |
| Attendance Mode | Mode retrieval and update |

### KioskTest (20 tests)

Tests kiosk device management:

| Category | Tests |
|----------|-------|
| Generate Code | Kiosk mode required, heartbeat update |
| CRUD | List, create, view, update kiosks |
| Token | Regenerate secret token |
| Authorization | Admin-only endpoints |

### ReportsTest (22 tests)

Tests reporting and analytics:

| Category | Tests |
|----------|-------|
| Individual | Daily/weekly/monthly/yearly summaries |
| Logs | Worker logs with date range |
| Flagged | Admin-only flagged logs |
| All Workers | Aggregated reports for all workers |
| Authorization | Worker can only see own reports |

## Running Tests

### Basic Commands

```bash
# Run all tests
docker compose exec app php artisan test

# Run only unit tests
docker compose exec app php artisan test tests/Unit

# Run only feature tests
docker compose exec app php artisan test tests/Feature

# Run specific test file
docker compose exec app php artisan test tests/Feature/Api/AuthTest.php

# Run specific test method
docker compose exec app php artisan test --filter=test_user_can_login_with_email

# Verbose output
docker compose exec app php artisan test --verbose

# Stop on first failure
docker compose exec app php artisan test --stop-on-failure
```

### Using PHPUnit Directly

```bash
docker compose exec app ./vendor/bin/phpunit tests/Unit
docker compose exec app ./vendor/bin/phpunit tests/Feature/Api/AuthTest.php
```

### Coverage Report

```bash
# Requires Xdebug
docker compose exec app php artisan test --coverage
docker compose exec app php artisan test --coverage-html=coverage
```

## Test Coverage Summary

| Category | Files | Tests | Assertions |
|----------|-------|-------|------------|
| **Unit Tests** | 17 | 129 | ~320 |
| Services | 2 | 26 | ~65 |
| Models | 5 | 42 | ~105 |
| Events | 2 | 11 | ~28 |
| Requests | 4 | 26 | ~65 |
| Resources | 2 | 8 | ~20 |
| Exceptions | 1 | 15 | ~38 |
| **Feature Tests** | 7 | 129 | ~424 |
| Auth | 1 | 19 | ~57 |
| TOTP | 1 | 16 | ~48 |
| Attendance | 1 | 14 | ~39 |
| Sync | 1 | 16 | ~51 |
| Settings | 1 | 21 | ~52 |
| Kiosk | 1 | 20 | ~64 |
| Reports | 1 | 22 | ~102 |
| **Total** | **24** | **258** | **~744** |

## Writing New Tests

### Unit Test Template

```php
<?php

namespace Tests\Unit\Services;

use App\Services\MyService;
use PHPUnit\Framework\TestCase;

class MyServiceTest extends TestCase
{
    private MyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MyService();
    }

    public function test_it_does_something(): void
    {
        $result = $this->service->doSomething('input');

        $this->assertEquals('expected', $result);
    }
}
```

### Feature Test Template

```php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_access_endpoint(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
        ]);
        $token = $user->createToken('auth_token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/my-endpoint');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/my-endpoint');

        $response->assertStatus(401);
    }
}
```

### Best Practices

1. **One assertion concept per test** - Each test should verify one behavior
2. **Descriptive test names** - `test_user_can_login_with_email` not `test_login`
3. **Arrange-Act-Assert pattern** - Setup, execute, verify
4. **Use factories** - `User::factory()->create()` for consistent test data
5. **Test edge cases** - Empty inputs, null values, boundary conditions
6. **Test error paths** - Unauthorized access, validation failures, not found
7. **Keep tests independent** - No test should depend on another test's state
8. **Mock external services** - Don't make real HTTP calls or use real APIs

### What NOT to Test

- Laravel framework code (it's already tested)
- Simple getters/setters without logic
- Private methods directly (test through public interface)
- Database migrations (test the resulting behavior instead)
- Third-party package internals
