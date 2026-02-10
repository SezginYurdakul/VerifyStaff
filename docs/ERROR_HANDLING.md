# Error & Exception Handling Guide

This document describes the error and exception handling architecture used across the VerifyStaff application, covering both the Laravel API backend and the React (TypeScript) frontend.

---

## Table of Contents

1. [API Error Response Format](#1-api-error-response-format)
2. [HTTP Status Codes](#2-http-status-codes)
3. [Backend: Laravel Exception Handling](#3-backend-laravel-exception-handling)
   - [Global Exception Handler](#31-global-exception-handler)
   - [Custom ApiException Class](#32-custom-apiexception-class)
   - [Form Request Validation](#33-form-request-validation)
   - [Controller-Level Error Handling](#34-controller-level-error-handling)
   - [Logging](#35-logging)
4. [Frontend: React Error Handling](#4-frontend-react-error-handling)
   - [Axios Interceptors](#41-axios-interceptors)
   - [TypeScript Error Types](#42-typescript-error-types)
   - [React Query Error Callbacks](#43-react-query-error-callbacks)
   - [Network & Offline Error Handling](#44-network--offline-error-handling)
   - [Sync Service Error Handling](#45-sync-service-error-handling)
5. [Error Code Reference](#5-error-code-reference)
6. [Best Practices](#6-best-practices)

---

## 1. API Error Response Format

All API errors follow a consistent JSON structure:

### Error Response

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human-readable error description.",
    "details": {}
  }
}
```

| Field             | Type     | Required | Description                                    |
|-------------------|----------|----------|------------------------------------------------|
| `success`         | boolean  | Yes      | Always `false` for errors                      |
| `error.code`      | string   | Yes      | Machine-readable error code (e.g. `NOT_FOUND`) |
| `error.message`   | string   | Yes      | Human-readable description                     |
| `error.details`   | object   | No       | Field-level validation errors (key → messages)  |
| `meta`            | object   | No       | Additional context metadata                    |

### Validation Error Response

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "identifier": ["Email, phone number, or employee ID is required."],
      "password": ["The password field is required."]
    }
  }
}
```

### Success Response

```json
{
  "message": "Operation successful.",
  "data": { }
}
```

### Debug Mode (Development Only)

When `APP_DEBUG=true`, unhandled exceptions include a `debug` block:

```json
{
  "success": false,
  "error": {
    "code": "SERVER_ERROR",
    "message": "Detailed error message visible in debug mode."
  },
  "debug": {
    "exception": "RuntimeException",
    "file": "/app/Http/Controllers/ExampleController.php",
    "line": 42,
    "trace": [ ]
  }
}
```

> **Important:** The `debug` block is **never** included in production (`APP_DEBUG=false`). In production, unhandled exceptions return a generic `"An unexpected error occurred."` message.

---

## 2. HTTP Status Codes

| Status | Code Constant          | Usage                                              |
|--------|------------------------|----------------------------------------------------|
| 400    | `BAD_REQUEST`          | Invalid input, malformed parameters                |
| 401    | `UNAUTHENTICATED`      | Missing or expired authentication token            |
| 403    | `FORBIDDEN`            | Authenticated but lacking permission (role-based)  |
| 404    | `NOT_FOUND`            | Resource or route not found                        |
| 405    | `METHOD_NOT_ALLOWED`   | Wrong HTTP method for the endpoint                 |
| 409    | `CONFLICT`             | Duplicate resource (e.g. duplicate attendance scan) |
| 422    | `VALIDATION_ERROR`     | Request validation failed (field-level errors)     |
| 500    | `SERVER_ERROR`         | Unhandled server exception                         |
| 503    | `SERVICE_UNAVAILABLE`  | Service temporarily unavailable                    |

---

## 3. Backend: Laravel Exception Handling

### 3.1 Global Exception Handler

**File:** `bootstrap/app.php`

All exceptions are caught by the global handler configured in `withExceptions()`. The handler renders JSON responses for API requests (`expectsJson()` or `api/*` routes) with a consistent format.

Exception types are handled in priority order:

| Priority | Exception Type                  | Status | Error Code            |
|----------|--------------------------------|--------|-----------------------|
| 1        | `ApiException`                 | Varies | Varies (custom)       |
| 2        | `ValidationException`          | 422    | `VALIDATION_ERROR`    |
| 3        | `AuthenticationException`      | 401    | `UNAUTHENTICATED`     |
| 4        | `ModelNotFoundException`        | 404    | `NOT_FOUND`           |
| 5        | `NotFoundHttpException`        | 404    | `NOT_FOUND`           |
| 6        | `MethodNotAllowedHttpException`| 405    | `METHOD_NOT_ALLOWED`  |
| 7        | `HttpException`                | Varies | `HTTP_ERROR`          |
| 8        | `Throwable` (fallback)         | 500    | `SERVER_ERROR`        |

**Key behaviors:**
- `ModelNotFoundException` automatically extracts the model name (e.g. `"User not found."`)
- The fallback handler includes debug info only when `APP_DEBUG=true`
- Stack traces are limited to the 5 most recent frames in debug output

### 3.2 Custom ApiException Class

**File:** `app/Exceptions/ApiException.php`

A custom exception class with factory methods for common error scenarios:

```php
// Constructor
new ApiException(
    message: 'Error description',
    statusCode: 400,
    errorCode: 'ERROR_CODE',
    errors: [],    // Optional field-level details
    meta: [],      // Optional metadata
    previous: null // Optional previous exception
);
```

#### Factory Methods

| Method                            | Status | Error Code             | Use Case                         |
|-----------------------------------|--------|------------------------|----------------------------------|
| `ApiException::notFound()`        | 404    | `NOT_FOUND`            | Resource not found               |
| `ApiException::unauthorized()`    | 401    | `UNAUTHORIZED`         | Custom auth failure              |
| `ApiException::forbidden()`       | 403    | `FORBIDDEN`            | Role/permission denied           |
| `ApiException::badRequest()`      | 400    | `BAD_REQUEST`          | Invalid input                    |
| `ApiException::validation()`      | 422    | `VALIDATION_ERROR`     | Business logic validation        |
| `ApiException::conflict()`        | 409    | `CONFLICT`             | Duplicate/conflict detection     |
| `ApiException::serverError()`     | 500    | `SERVER_ERROR`         | Internal error                   |
| `ApiException::invalidDate()`     | 400    | `INVALID_DATE_FORMAT`  | Date parsing failure             |
| `ApiException::serviceUnavailable()` | 503 | `SERVICE_UNAVAILABLE`  | Temporary unavailability         |

#### Usage Examples

```php
// Throw a not-found error
throw ApiException::notFound('Worker');
// → 404: { "error": { "code": "NOT_FOUND", "message": "Worker not found." } }

// Throw a bad request with field errors
throw ApiException::badRequest('Invalid parameters', [
    'date' => 'Date must be in the future.'
]);

// Throw a conflict error
throw ApiException::conflict('Duplicate scan detected.');
```

### 3.3 Form Request Validation

**Files:** `app/Http/Requests/Api/*.php`

Form Requests handle input validation declaratively with custom error messages:

```php
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identifier' => 'required|string',
            'password'   => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'identifier.required' => 'Email, phone number, or employee ID is required.',
            'password.required'   => 'The password field is required.',
        ];
    }
}
```

When validation fails, the global handler returns a `422` response with field-level `details`.

### 3.4 Controller-Level Error Handling

Controllers use several patterns:

**Pattern 1: Throwing ValidationException**
```php
// AuthController — invalid credentials
throw ValidationException::withMessages([
    'identifier' => ['The provided credentials are incorrect.'],
]);
```

**Pattern 2: Direct JSON responses for business rules**
```php
// ReportsController — date range validation
if ($to->isBefore($from)) {
    return response()->json([
        'message' => 'End date cannot be before start date',
    ], 422);
}
```

**Pattern 3: Authorization checks**
```php
// Role-based access control
if (!$user->isAdmin()) {
    return response()->json([
        'message' => 'Only administrators can perform this action.',
    ], 403);
}
```

**Pattern 4: Collecting errors in batch operations**
```php
// SyncController — partial failure tracking
$errors = [];
foreach ($logs as $log) {
    if (!$worker) {
        $errors[] = [
            'worker_id' => $log['worker_id'],
            'reason'    => 'Worker not found',
        ];
        continue;
    }
    // ... process valid log
}

return response()->json([
    'synced_count' => $synced,
    'error_count'  => count($errors),
    'errors'       => $errors,
]);
```

### 3.5 Logging

**File:** `config/logging.php`

- **Default channel:** `stack` (configurable via `LOG_CHANNEL` env var)
- **Log level:** Configurable via `LOG_LEVEL` env var (default: `debug`)
- **Log file:** `storage/logs/laravel.log`
- **Supported drivers:** Single file, daily rotation, Slack, Syslog, stderr, null

---

## 4. Frontend: React Error Handling

### 4.1 Axios Interceptors

**File:** `frontend/src/lib/api.ts`

A centralized Axios instance with request and response interceptors:

**Request Interceptor** — Attaches the Bearer token to every API request:
```typescript
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});
```

**Response Interceptor** — Handles `401 Unauthenticated` globally:
```typescript
api.interceptors.response.use(
  (response) => response,
  (error: AxiosError<ApiError>) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('token');
      localStorage.removeItem('auth-storage');
      if (!window.location.pathname.includes('/login')) {
        window.location.href = '/login';
      }
    }
    return Promise.reject(error);
  }
);
```

This ensures that expired or revoked tokens trigger an automatic redirect to the login page across the entire application.

### 4.2 TypeScript Error Types

**File:** `frontend/src/types/index.ts`

```typescript
export interface ApiError {
  message: string;
  error?: {
    code: string;
    details?: Record<string, string[]>;
  };
}

export interface ApiResponse<T> {
  message?: string;
  data?: T;
}
```

### 4.3 React Query Error Callbacks

The frontend uses TanStack React Query's `useMutation` for API calls with structured `onError` handlers.

**Pattern: Differentiated error messages (LoginPage)**
```typescript
onError: (err: AxiosError<ApiError>) => {
  // 1. Network error — no response received
  if (!err.response) {
    setError('Unable to connect to server. Please check your internet connection.');
    return;
  }
  // 2. Server error (5xx)
  if (err.response.status >= 500) {
    setError('Server error. Please try again later.');
    return;
  }
  // 3. Client error (4xx) — use server message or fallback
  setError(err.response.data?.message || 'Invalid credentials. Please try again.');
}
```

**Pattern: Offline fallback with local storage (ScannerPage)**
```typescript
onError: async (err) => {
  const axiosErr = err as AxiosError<ApiError>;

  // Network error → save scan locally for later sync
  if (!axiosErr.response) {
    const worker = await getStaffById(workerId);
    handleSuccessfulScan(worker, scanType, true /* provisional */);
    return;
  }

  // 403 → specific permission message
  if (axiosErr.response?.status === 403) {
    message = axiosErr.response?.data?.message || 'Access denied.';
  }

  setLastResult({ success: false, message });
}
```

### 4.4 Network & Offline Error Handling

**Online/Offline Detection (AppLayout)**
```typescript
const [isOnline, setIsOnline] = useState(navigator.onLine);

useEffect(() => {
  const handleOnline = () => setIsOnline(true);
  const handleOffline = () => setIsOnline(false);
  window.addEventListener('online', handleOnline);
  window.addEventListener('offline', handleOffline);
  return () => {
    window.removeEventListener('online', handleOnline);
    window.removeEventListener('offline', handleOffline);
  };
}, []);
```

**Offline Mode UI Banner (ScannerPage)**
```tsx
{!isOnline && (
  <Card className="bg-yellow-50 border-2 border-yellow-300">
    <div className="font-medium text-yellow-800">Offline Mode</div>
    <div className="text-sm text-yellow-700">
      Scans will be saved locally and synced when online
    </div>
  </Card>
)}
```

### 4.5 Sync Service Error Handling

**File:** `frontend/src/lib/syncService.ts`

The sync service manages offline-first data synchronization with robust error handling:

```
syncPendingLogs()
  ├── Guard: skip if offline or already syncing
  ├── Partition logs by source (kiosk vs representative)
  ├── Sync representative logs
  │   ├── Success → mark as 'synced'
  │   ├── Per-worker error → mark as 'failed'
  │   └── 403 Forbidden → keep as 'pending' (mode mismatch)
  ├── Sync kiosk logs
  │   ├── Success → mark as 'synced'
  │   ├── Per-event error → mark as 'failed'
  │   └── 403 Forbidden → keep as 'pending' (mode mismatch)
  ├── Clean up synced logs from IndexedDB
  └── Update sync store (pending count, last sync time, error)
```

**Key behaviors:**
- `403 Forbidden` errors during sync are silently handled (role/mode mismatch expected during transitions)
- All other errors propagate and are stored in the sync store for UI display
- Individual log failures don't block the rest of the batch
- A `syncInProgress` flag prevents concurrent sync operations

**Sync status lifecycle:**

```
pending → syncing → synced   (success)
                  → failed   (per-item error)
                  → pending  (403 / network error — retry later)
```

---

## 5. Error Code Reference

| Error Code             | HTTP | Thrown By                       | Description                              |
|------------------------|------|---------------------------------|------------------------------------------|
| `VALIDATION_ERROR`     | 422  | FormRequest, Global Handler     | Input validation failed                  |
| `UNAUTHENTICATED`      | 401  | Global Handler (middleware)     | No valid authentication token            |
| `UNAUTHORIZED`         | 401  | `ApiException::unauthorized()`  | Custom authorization failure             |
| `FORBIDDEN`            | 403  | `ApiException::forbidden()`     | Role-based access denied                 |
| `NOT_FOUND`            | 404  | Global Handler, ApiException    | Resource or route not found              |
| `METHOD_NOT_ALLOWED`   | 405  | Global Handler                  | Invalid HTTP method                      |
| `CONFLICT`             | 409  | `ApiException::conflict()`      | Duplicate resource detected              |
| `BAD_REQUEST`          | 400  | `ApiException::badRequest()`    | Malformed or invalid request             |
| `INVALID_DATE_FORMAT`  | 400  | `ApiException::invalidDate()`   | Unparseable date value                   |
| `SERVER_ERROR`         | 500  | Global Handler, ApiException    | Unhandled or internal server error       |
| `SERVICE_UNAVAILABLE`  | 503  | `ApiException::serviceUnavailable()` | Temporary service outage            |
| `HTTP_ERROR`           | *    | Global Handler                  | Generic Symfony HTTP exception           |

---

## 6. Best Practices

### Backend

1. **Use `ApiException` factory methods** instead of raw `response()->json()` for consistency.
2. **Use FormRequest classes** for all input validation — keeps controllers clean.
3. **Never expose internals in production** — debug info is gated behind `APP_DEBUG`.
4. **Collect partial errors in batch operations** — return per-item errors instead of failing the entire request.
5. **Use appropriate status codes** — `422` for validation, `409` for duplicates, `403` for authorization.

### Frontend

1. **Always check `!err.response` first** — this indicates a network/connectivity error, not a server error.
2. **Use the global 401 interceptor** — don't handle token expiry in individual components.
3. **Store operations offline** — if a network error occurs during a critical operation (scan, check-in), save it to IndexedDB for later sync.
4. **Show specific error messages** — differentiate between network errors, server errors, and business logic errors for the user.
5. **Use typed error interfaces** — always cast to `AxiosError<ApiError>` for type-safe error handling.

### General

1. **Consistent response format** — all errors use the `{ success, error: { code, message, details } }` structure.
2. **Machine-readable codes + human-readable messages** — clients can switch on `error.code`, users see `error.message`.
3. **Graceful degradation** — the app functions offline with local storage and syncs when connectivity returns.
4. **No silent failures** — errors are either displayed to the user, logged, or stored in sync state for review.
