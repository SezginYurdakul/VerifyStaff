# 🚀 VerifyStaff: Offline-First Attendance System

**VerifyStaff** is a lightweight, high-reliability attendance tracking solution designed for environments with unstable or no internet connection. It eliminates the need for expensive biometric hardware by using a secure, peer-to-peer QR scanning model.

---

## 📌 Project Concept

The system operates on a **dual-mode validation model**:

### Representative Mode (Default)
1. **Workers** generate a dynamic, time-synced QR code (no internet needed).
2. **Representatives** scan these codes using their mobile device (works offline).
3. **Data** is stored locally on the representative's device and synced to the **Laravel Server** once an internet connection is established.

### Kiosk Mode
1. **Kiosk devices** display a dynamic, time-synced QR code.
2. **Workers** scan the kiosk QR code using their mobile device.
3. **Attendance** is recorded directly on the server (requires internet), with offline fallback for network failures.

---

## 🛠 Tech Stack

| Component | Technology | Role |
| :--- | :--- | :--- |
| **Backend** | Laravel 11 (PHP 8.3+) | Central database, Auth, Reporting API |
| **Frontend** | React (Vite) | Progressive Web App (PWA) interface |
| **Database** | MySQL 8.0 | Server-side persistent storage |
| **Offline DB** | IndexedDB (Dexie.js) | Browser-side storage for offline logs |
| **Security** | TOTP Algorithm | Generates unhackable, 30-second QR codes |
| **Auth** | Laravel Sanctum | Personal Access Token (Bearer) authentication |
| **Queue** | Database driver | Async job processing (invite emails, summaries) |
| **Deployment** | Docker Compose | Containerized app, nginx, MySQL, queue worker |

---

## 📅 4-Stage Roadmap

### 1. Infrastructure & Backend (Laravel) ✅ COMPLETED
*Focus: The "Brain" and Administrative Control.*

- [x] **Database Schema:**
    - `users`: id, name, email, phone, employee_id, role [admin/representative/worker], secret_token, status, invite_token, invite_expires_at, invite_accepted_at, soft deletes
    - `attendance_logs`: id, event_id, worker_id, rep_id, kiosk_id, type [in/out], device_time, device_timezone, sync_time, sync_status, work_minutes, flagged, flag_reason, latitude, longitude, paired_log_id, is_late, is_early_departure, is_overtime
    - `work_summaries`: id, worker_id, period_type, period_start/end, total_minutes, overtime, late_arrivals, etc.
    - `settings`: id, key, group, value, type, description (seeded with defaults)
    - `kiosks`: id, name, code, secret_token, location, latitude/longitude, status, last_heartbeat_at
    - `departments`: id, name, description, timestamps
- [x] **Authentication:** Laravel Sanctum for secure mobile-to-server communication
    - Admin-driven user registration (no public registration)
    - Email invitation with secure token (queued via database queue)
    - Password setting via invite link
    - Login with multiple identifiers (email/phone/employee_id)
    - Single-device login enforcement for workers
    - Token refresh
    - Logout
- [x] **Core APIs:**
    - `GET /api/v1/sync/staff`: Download worker validation list for Representatives
    - `POST /api/v1/sync/logs`: Process bulk attendance uploads (with toggle mode support)
    - `GET /api/v1/time`: Server time synchronization
- [x] **Attendance APIs:**
    - `POST /api/v1/attendance/self-check`: Kiosk mode self-check
    - `POST /api/v1/attendance/sync-offline`: Sync offline kiosk logs (flagged as unverified)
    - `GET /api/v1/attendance/status`: Current check-in status
- [x] **TOTP APIs:**
    - `GET /api/v1/totp/generate`: Generate TOTP code for workers
    - `POST /api/v1/totp/verify`: Verify TOTP code (for reps/admins)
- [x] **Settings APIs:**
    - CRUD for system settings (single + bulk update)
    - Settings by group
    - Work hours configuration
    - Shift management
    - Working days configuration
    - Attendance mode switching (representative/kiosk)
- [x] **Kiosk APIs:**
    - Full CRUD for kiosk management
    - Code generation for kiosk display (public endpoint)
    - Token regeneration
- [x] **User Management APIs:**
    - Full CRUD with soft delete
    - Restore soft-deleted users
    - Force delete (permanent)
    - Resend invitation email
    - Role/status/department filtering
- [x] **Department APIs:**
    - Full CRUD for department management
    - Worker count per department
- [x] **Dashboard APIs:**
    - Overview stats (total workers, attendance today, anomalies)
    - Attendance trends
    - Detected anomalies
- [x] **Reports APIs:**
    - Daily/Weekly/Monthly/Yearly summaries (single worker & all workers)
    - Flagged logs for anomaly review
    - Worker attendance logs with filtering
- [x] **Services:**
    - TotpService: TOTP generation and verification with ±1 window tolerance
    - AttendanceSyncService: Attendance type detection, work hour calculations, flagging
    - ReportService: Summary calculations and report generation
    - WorkSummaryService: Period-based summary calculations
    - InviteService: User invitation workflow
    - DashboardOverviewService: KPI calculations (total workers, attendance today, anomalies)
    - DashboardTrendsService: Attendance trend data for charts (last N days)
    - DashboardAnomalyService: Anomaly detection and flagged entries for review
    - AuditLogger: Activity logging
- [x] **Form Requests (18 classes):**
    - LoginRequest, ValidateInviteRequest, AcceptInviteRequest
    - SelfCheckRequest, SyncLogsRequest, SyncOfflineLogsRequest
    - StoreUserRequest, UpdateUserRequest
    - StoreDepartmentRequest, UpdateDepartmentRequest
    - StoreKioskRequest, UpdateKioskRequest
    - UpdateSettingRequest, UpdateBulkSettingsRequest, UpdateShiftsRequest, UpdateWorkingDaysRequest, UpdateAttendanceModeRequest
    - VerifyTotpRequest
- [x] **API Resources:**
    - UserResource (role-based field visibility)
    - WorkerResource (simplified for sync)
    - AttendanceLogResource
    - KioskResource
    - DepartmentResource (with worker count)
- [x] **Events & Listeners:**
    - TotpVerified event → LogTotpVerification listener
    - SettingChanged event → LogSettingChange listener
- [x] **Mail:**
    - UserInviteMail (implements ShouldQueue for async delivery)
- [x] **Jobs:**
    - CalculateWorkSummary (async summary recalculation)
    - ProcessAttendanceSync (async log processing)
- [x] **Testing:**
    - 414 tests (1240 assertions) passing in ~3 seconds
    - 12 Feature test files covering all API endpoints
    - In-memory SQLite for fast test execution
    - Reduced bcrypt rounds for test performance



### 2. Frontend & PWA Integration (React) ✅ COMPLETED
*Focus: Mobile experience and Offline engine.*

- [x] **PWA Configuration:** `vite-plugin-pwa` with "Add to Home Screen" and offline asset caching
- [x] **Service Workers:** Auto-update, skipWaiting, clientsClaim for instant offline access
- [x] **Workbox Caching:** NetworkFirst strategy for API calls (10s timeout), auto-cleanup of outdated caches
- [x] **IndexedDB Setup:** Local mirror of staff database for offline verification (Dexie.js)
- [x] **UI Components:**
    - [x] Login screen (no public registration - admin invite only)
    - [x] Worker QR code display with TOTP countdown
    - [x] Representative scanner interface with offline support
    - [x] Kiosk mode display (KioskDisplayPage)
    - [x] Worker kiosk scan page (WorkerKioskScanPage) with offline fallback
    - [x] Kiosk selection page
    - [x] Admin dashboard with trends and anomaly alerts
    - [x] Anomalies detail page
    - [x] Reports page with filtering, pagination, and per-page selector
    - [x] Worker detail page
    - [x] **User Management (Admin):**
        - [x] User list with role/status/department filters
        - [x] Create user with email invite
        - [x] Edit/Delete users
        - [x] Restore soft-deleted users
        - [x] Resend invitation
        - [x] Set password page (invite flow)
    - [x] **Department Management (Admin):**
        - [x] Department list with CRUD
    - [x] **Settings Pages:**
        - [x] General settings (QR refresh, auto checkout)
        - [x] Attendance mode settings (representative/kiosk, toggle mode)
        - [x] Shifts settings (work hours, multiple shifts, thresholds)
        - [x] Working days configuration
        - [x] Kiosk management
- [x] **State Management:** authStore, syncStore (Zustand)
- [x] **API Layer:** Full TypeScript API modules (auth, sync, attendance, kiosk, totp, dashboard, reports, departments, users, settings)
- [x] **Reusable Components:** Button, Card, Input, Modal, SyncStatusBadge
- [x] **Responsive Layouts:** AppLayout, AuthLayout with mobile optimization
- [x] **Brand Styling:** Custom color palette, focus states, mobile-friendly UI



### 3. Dynamic QR & Security Logic ✅ COMPLETED
*Focus: Preventing fraud and screenshots.*

- [x] **Worker Logic (Backend):**
    - TOTP-based code generation (refreshes every 30s)
    - ±1 window tolerance for clock drift
- [x] **Worker Logic (Frontend):**
    - QR code display with visual countdown
    - Visual "Live Feed" indicator (prevents using old screenshots)
    - Auto-refresh countdown timer
- [x] **Representative Logic:**
    - High-speed camera integration using `html5-qrcode`
    - **Local Validation:** Comparing the QR timestamp with the representative's device clock
    - Offline queue for pending syncs
- [x] **Kiosk Logic:**
    - Kiosk displays dynamic QR code (public endpoint)
    - Worker scans and self-checks via API
    - Offline fallback: saves to IndexedDB, syncs when online (flagged as unverified)
- [x] **Feedback System:** Visual (green/red) feedback for scan results, success/error messages



### 4. Testing, Sync & Deployment ✅ COMPLETED
*Focus: Data integrity and Deployment.*

- [x] **Conflict Resolution (Backend):**
    - SHA256 event_id for idempotent uploads
    - Duplicate detection with configurable time window
    - Flagging system for anomalies (late arrival, missing checkout, offline sync)
- [x] **Toggle Mode:** Support for alternating check-in/check-out based on last status
- [x] **Auto-Sync (Frontend):**
    - Background logic to detect internet and push data automatically
    - Role-based sync routing (rep logs → `/sync/logs`, kiosk logs → `/attendance/sync-offline`)
    - Chunked uploads (500 items per batch)
    - Conflict handling with error matching by event_id
    - Online event listener for immediate sync on reconnection
- [x] **Deployment:**
    - Docker Compose setup (app, nginx, MySQL, scheduler, queue worker, node dev server)
    - PHP 8.4-FPM with Composer
    - Nginx reverse proxy (port 8000)
    - Queue worker with automatic retry
    - Laravel scheduler container

---

## 🚫 Non-Goals

These are explicitly out of scope for this project:

- **Biometric verification** (fingerprint, face recognition) — replaced by TOTP QR codes
- **Real-time GPS enforcement** — GPS is captured for audit, not used as a blocker
- **Payroll processing** — the system tracks attendance, not wages or salary calculations
- **Multi-tenant architecture** — single-organization deployment per instance
- **Chat or messaging** — no in-app communication between users
- **Hardware integration** — no dedicated scanners, NFC readers, or turnstile systems

---

## 📋 Assumptions

- Workers carry a smartphone capable of running a modern PWA (Chrome/Safari)
- Representative devices have a functioning camera for QR scanning
- Server time is authoritative for TOTP validation; device clocks may drift ±1 minute
- Internet connectivity is available at least once per day for data sync
- Admin is responsible for onboarding users (no self-registration)
- A single representative can handle scanning for a group of workers at one location
- Kiosk devices remain at fixed locations with stable power supply

---

## ⚡ Failure Scenarios & Mitigations

| Scenario | Impact | Mitigation |
|----------|--------|------------|
| Rep device reset/lost before sync | Unsaved attendance logs lost | IndexedDB persists across browser sessions; data survives app restarts but not full device wipe. Reps should sync at every connectivity window. |
| Worker clock drift > 2 minutes | TOTP code rejected by rep | ±1 window tolerance (90s effective). If drift exceeds this, worker must sync device clock. Server time endpoint (`GET /time`) available for manual reference. |
| Kiosk offline > 24 hours | Workers cannot check in via kiosk | Offline fallback stores logs in worker's IndexedDB, syncs later with "TOTP not verified" flag. Admin reviews flagged entries. |
| Duplicate sync from multiple devices | Potential double-counted attendance | SHA256 event_id ensures idempotency — duplicate uploads are silently ignored. |
| Rep scans same worker twice in 1 minute | Duplicate record | Same event_id generated (worker + rep + timestamp + type hash) — second upload rejected as duplicate. |
| Server down during sync attempt | Logs fail to upload | Logs remain in IndexedDB with retry on next connectivity. Chunked uploads (500/batch) prevent timeout issues. |
| Invite email not delivered | Worker cannot set password | Admin can resend invitation via `POST /users/{id}/resend-invite`. Token validity is configurable. |
| Worker loses phone mid-shift | No check-out recorded | System flags missing checkout automatically. Admin resolves via anomaly review. |

---

## 🔒 Security Measures

* **Time-Lock:** QR codes are valid for only 30 seconds (±1 window = 90s effective)
* **Device Binding:** Each worker is tied to a specific `secret_token` generated by the server
* **Single-Device Login:** Workers limited to one active session (tokens revoked on new login)
* **GPS Capture:** Location is recorded during scans for audit purposes
* **Idempotent Uploads:** SHA256-based event_id prevents duplicate records
* **Bearer Token Auth:** Sanctum Personal Access Tokens (non-JWT)
* **Offline Flagging:** Offline kiosk scans are flagged as "TOTP not verified" for admin review
* **Invite-Only Registration:** No public signup, admin creates users with queued email invites

### Accepted Risks

| Risk | Rationale |
|------|-----------|
| Offline kiosk scans are not TOTP-verified | Acceptable trade-off for offline availability. All offline scans are flagged for admin review. |
| GPS spoofing is possible | GPS is treated as an audit signal, not a hard blocker. Spoofed coordinates can be detected via anomaly patterns (e.g., impossible travel). |
| Bearer tokens have no expiry by default | Sanctum tokens persist until logout or revocation. Workers are limited to single-device sessions, reducing exposure. Token refresh is available. |
| IndexedDB can be cleared by user | Browser storage is not tamper-proof. Critical data integrity relies on server-side validation after sync. |
| TOTP secret stored in database | If database is compromised, TOTP codes can be generated. Mitigated by standard database security practices and bcrypt-hashed passwords. |

---

## 📊 Database Schema

### Users Table
```
id | name | email | phone | employee_id | role | secret_token | status |
department_id | password | invite_token | invite_expires_at | invite_accepted_at |
deleted_at | timestamps
```

### Attendance Logs Table
```
id | event_id | worker_id | rep_id | kiosk_id | type | device_time | device_timezone |
sync_time | sync_attempt | offline_duration_seconds | sync_status | flagged | flag_reason |
latitude | longitude | paired_log_id | work_minutes | is_late | is_early_departure |
is_overtime | timestamps
```

### Work Summaries Table
```
id | worker_id | period_type | period_start | period_end | total_minutes |
regular_minutes | overtime_minutes | days_worked | days_absent |
late_arrivals | early_departures | missing_checkouts | missing_checkins | timestamps
```

### Settings Table
```
id | key | group | value | type | description | timestamps
```

### Kiosks Table
```
id | name | code | secret_token | location | latitude | longitude | status |
last_heartbeat_at | timestamps
```

### Departments Table
```
id | name | description | timestamps
```

---

## ⚠️ Critical Implementation Details

### 1. Clock Synchronization Strategy

**Problem:** TOTP uses 30-second windows, but devices may have clock drift up to ±1 minute.

**Solution: Multi-Window TOTP Validation** ✅ Implemented
- Accept codes from **3 consecutive time windows**: previous (-30s), current, and next (+30s)
- This provides a **90-second effective validity window** while maintaining security
- Implementation in `TotpService::verifyCode()` with `window: 1` parameter

### 2. Attendance Mode Support

**Two modes implemented:**

| Mode | How it works | Use case |
|------|--------------|----------|
| **Representative** | Worker shows QR, rep scans | Field work, construction sites |
| **Kiosk** | Kiosk shows QR, worker scans | Office, factory with fixed entry points |

Mode is configurable via Settings API. Both modes support offline operation with automatic sync.

### 3. Offline Data Conflict Resolution

**A. Unique Event Identification:**
```
event_id = SHA256(worker_id + rep_id + device_timestamp + scan_type)
```

**B. Conflict Resolution Rules:**

| Conflict Type | Resolution Rule |
|--------------|-----------------|
| Duplicate exact scan | Keep first received, ignore duplicates (idempotent) |
| Same worker, same minute, different reps | Keep BOTH records, flag for admin review |
| Check-in without check-out | Auto-generate "missing checkout" flag |
| Check-out without check-in | Flag as anomaly, require admin resolution |
| Offline kiosk sync | Flag as "Offline kiosk sync - TOTP not verified" |

**C. Toggle Mode:** ✅ Implemented
- `POST /api/v1/sync/logs` supports `toggle_mode: true`
- Automatically determines check-in or check-out based on last status

### 4. Offline Sync Architecture

**Frontend sync flow:**
1. Attendance logs saved to IndexedDB when offline
2. `online` event listener triggers sync attempt
3. Logs split by source: rep logs vs kiosk logs
4. Each group synced to its respective endpoint in 500-item chunks
5. Successfully synced logs removed from IndexedDB
6. Failed logs retained with error tracking for retry

### 5. Invite-Based Onboarding

**Flow:**
1. Admin creates user via `POST /api/v1/users` → invite email queued (database queue)
2. Worker receives email with unique invite link
3. Worker validates token via `POST /api/v1/invite/validate`
4. Worker sets password via `POST /api/v1/invite/accept` → gets auth token
5. Worker can now login normally

---

## 📈 Summary of Data Flow

### Representative Mode
1. **Morning (Online):** Rep syncs latest staff list via `GET /api/v1/sync/staff`
2. **On-Site (Offline):** Rep scans workers. Logs saved to Representative's phone (IndexedDB)
3. **Evening (Online):** App auto-detects internet and pushes logs to Laravel via `POST /api/v1/sync/logs`
4. **Admin Dashboard:** Employer views real-time reports via Reports API

### Kiosk Mode
1. **Kiosk device** displays dynamic QR code (refreshes every 30s)
2. **Worker** scans QR code with their phone
3. **Online:** Attendance recorded immediately via `POST /api/v1/attendance/self-check`
4. **Offline:** Log saved to IndexedDB, synced later via `POST /api/v1/attendance/sync-offline` (flagged)

---

## 📁 API Documentation

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/auth/login` | Login with email/phone/employee_id |
| POST | `/api/v1/auth/logout` | Logout (revoke token) |
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
| GET | `/api/v1/sync/staff` | Get staff list for offline validation |
| POST | `/api/v1/sync/logs` | Upload attendance logs |
| GET | `/api/v1/time` | Get server time |

### Attendance (Kiosk Mode)
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/attendance/self-check` | Worker self check-in/out |
| POST | `/api/v1/attendance/sync-offline` | Sync offline kiosk logs (flagged) |
| GET | `/api/v1/attendance/status` | Get current attendance status |

### TOTP
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/totp/generate` | Generate TOTP code (workers) |
| POST | `/api/v1/totp/verify` | Verify TOTP code (reps/admins) |

### Dashboard
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/dashboard/overview` | Overview stats |
| GET | `/api/v1/dashboard/trends` | Attendance trends |
| GET | `/api/v1/dashboard/anomalies` | Detected anomalies |

### Reports
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/reports/summary/{id}/daily` | Daily summary |
| GET | `/api/v1/reports/summary/{id}/weekly` | Weekly summary |
| GET | `/api/v1/reports/summary/{id}/monthly` | Monthly summary |
| GET | `/api/v1/reports/summary/{id}/yearly` | Yearly summary |
| GET | `/api/v1/reports/logs/{id}` | Worker's attendance logs |
| GET | `/api/v1/reports/all/daily` | All workers daily |
| GET | `/api/v1/reports/all/weekly` | All workers weekly |
| GET | `/api/v1/reports/all/monthly` | All workers monthly |
| GET | `/api/v1/reports/all/yearly` | All workers yearly |
| GET | `/api/v1/reports/flagged` | Flagged logs |

### Settings (Admin)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/settings` | List all settings |
| GET | `/api/v1/settings/group/{group}` | Get settings by group |
| GET | `/api/v1/settings/{key}` | Get setting |
| PUT | `/api/v1/settings/{key}` | Update setting |
| PUT | `/api/v1/settings` | Bulk update |
| GET | `/api/v1/settings/work-hours` | Get work hours |
| GET | `/api/v1/settings/attendance-mode` | Get attendance mode |
| PUT | `/api/v1/settings/config/shifts` | Update shifts |
| PUT | `/api/v1/settings/config/working-days` | Update working days |
| PUT | `/api/v1/settings/config/attendance-mode` | Change mode |

### Kiosks (Admin)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/kiosks` | List all kiosks |
| POST | `/api/v1/kiosks` | Create kiosk |
| GET | `/api/v1/kiosks/{code}` | Get kiosk |
| PUT | `/api/v1/kiosks/{code}` | Update kiosk |
| POST | `/api/v1/kiosks/{code}/regenerate-token` | Regenerate token |
| GET | `/api/v1/kiosk/{code}/code` | Get kiosk QR code (public) |

---

## 📊 Architecture Overview

### Backend (11 Controllers, 18 Form Requests, 5 Resources, 9 Services)

```
app/
├── Http/
│   ├── Controllers/Api/V1/
│   │   ├── AttendanceController    # Self-check, offline sync, status
│   │   ├── AuthController          # Login, logout, refresh, me
│   │   ├── DashboardController     # Overview, trends, anomalies
│   │   ├── DepartmentsController   # Department CRUD
│   │   ├── InviteController        # Validate & accept invitations
│   │   ├── KioskController         # Kiosk CRUD, QR generation
│   │   ├── ReportsController       # All report endpoints
│   │   ├── SettingsController      # Settings management
│   │   ├── SyncController          # Staff sync, log upload
│   │   ├── TotpController          # TOTP generate & verify
│   │   └── UsersController         # User CRUD, restore, force delete
│   ├── Requests/Api/               # 18 FormRequest classes
│   └── Resources/                  # UserResource, WorkerResource, etc.
├── Services/
│   ├── AttendanceSyncService       # Attendance processing logic
│   ├── TotpService                 # TOTP algorithm
│   ├── ReportService               # Report calculations
│   ├── WorkSummaryService          # Summary aggregations
│   ├── InviteService               # Invitation workflow
│   ├── Dashboard/
│   │   ├── DashboardOverviewService  # KPI calculations
│   │   ├── DashboardTrendsService    # Trend data for charts
│   │   └── DashboardAnomalyService   # Anomaly detection
│   └── AuditLogger                 # Activity logging
├── Models/                         # User, AttendanceLog, Department, Kiosk, WorkSummary, Setting
├── Mail/                           # UserInviteMail (queued)
├── Jobs/                           # CalculateWorkSummary, ProcessAttendanceSync
├── Events/                         # TotpVerified, SettingChanged
└── Listeners/                      # LogTotpVerification, LogSettingChange
```

### Frontend (React PWA)

```
frontend/src/
├── api/                            # TypeScript API modules (10 files)
├── features/
│   ├── auth/                       # LoginPage, SetPasswordPage
│   ├── worker/                     # WorkerDashboard, WorkerQRPage
│   ├── kiosk/                      # KioskDisplayPage, WorkerKioskScanPage, KioskSelectPage
│   ├── dashboard/                  # DashboardPage
│   ├── reports/                    # ReportsPage, WorkerDetailPage, AnomaliesPage
│   ├── scanner/                    # ScannerPage (representative mode)
│   ├── users/                      # UsersPage
│   ├── departments/                # DepartmentsPage
│   └── settings/                   # SettingsPage
├── components/                     # Reusable UI (Button, Card, Input, Modal)
├── layouts/                        # AppLayout, AuthLayout
├── stores/                         # authStore, syncStore (Zustand)
├── lib/                            # syncService, db (IndexedDB), api instance
└── types/                          # TypeScript interfaces
```
