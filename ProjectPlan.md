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
3. **Attendance** is recorded directly on the server (requires internet).

---

## 🛠 Tech Stack

| Component | Technology | Role |
| :--- | :--- | :--- |
| **Backend** | Laravel 11 (PHP 8.3+) | Central database, Auth, Reporting API |
| **Frontend** | React (Vite) | Progressive Web App (PWA) interface |
| **Database** | MySQL 8.0 | Server-side persistent storage |
| **Offline DB** | IndexedDB (Dexie.js) | Browser-side storage for offline logs |
| **Security** | TOTP Algorithm | Generates unhackable, 30-second QR codes |
| **Auth** | Laravel Sanctum | Token-based API authentication |

---

## 📅 4-Stage Roadmap

### 1. Infrastructure & Backend (Laravel) ✅ COMPLETED
*Focus: The "Brain" and Administrative Control.*

- [x] **Database Schema:**
    - `users`: id, name, email, phone, employee_id, role [admin/representative/worker], secret_token, status
    - `attendance_logs`: id, event_id, worker_id, rep_id, kiosk_id, type [in/out], device_time, sync_time, work_minutes, flags
    - `work_summaries`: id, worker_id, period_type, period_start/end, total_minutes, overtime, late_arrivals, etc.
    - `settings`: id, key, group, value, type, description (seeded with defaults)
    - `kiosks`: id, name, code, secret_token, location, latitude/longitude, status
- [x] **Authentication:** Laravel Sanctum for secure mobile-to-server communication
    - Register (email/phone/employee_id)
    - Login with multiple identifiers
    - Token refresh
    - Logout
- [x] **Core APIs:**
    - `GET /api/v1/sync/staff`: Download worker validation list for Representatives
    - `POST /api/v1/sync/logs`: Process bulk attendance uploads (with toggle mode support)
    - `GET /api/v1/time`: Server time synchronization
- [x] **Attendance APIs:**
    - `POST /api/v1/attendance/self-check`: Kiosk mode self-check
    - `GET /api/v1/attendance/status`: Current check-in status
- [x] **TOTP APIs:**
    - `GET /api/v1/totp/generate`: Generate TOTP code for workers
    - `POST /api/v1/totp/verify`: Verify TOTP code (for reps/admins)
- [x] **Settings APIs:**
    - CRUD for system settings
    - Work hours configuration
    - Shift management
    - Attendance mode switching (representative/kiosk)
- [x] **Kiosk APIs:**
    - CRUD for kiosk management
    - Code generation for kiosk display
    - Token regeneration
- [x] **Reports APIs:**
    - Daily/Weekly/Monthly/Yearly summaries (single worker & all workers)
    - Flagged logs for anomaly review
    - Worker attendance logs with filtering
- [x] **Services:**
    - TotpService: TOTP generation and verification with ±1 window tolerance
    - ReportService: Summary calculations and report generation
    - WorkSummaryService: Period-based summary calculations
    - AuditLogger: Activity logging
- [x] **Events & Listeners:**
    - TotpVerified event
    - SettingChanged event
    - Automatic logging via listeners
- [x] **Testing:**
    - 129 Unit tests (Services, Models, Events, Requests, Resources, Exceptions)
    - 129 Feature tests (All API endpoints)
    - High-performance testing environment (258 tests in ~1.5 seconds)



### 2. Frontend & PWA Integration (React)
*Focus: Mobile experience and Offline engine.*

- [ ] **PWA Configuration:** Setup `vite-plugin-pwa` to allow "Add to Home Screen" and offline asset caching.
- [ ] **Service Workers:** Logic to ensure the app opens instantly even in airplane mode.
- [ ] **IndexedDB Setup:** Creating a local mirror of the staff database to allow offline verification.
- [ ] **UI Components:**
    - Login/Register screens
    - Worker QR code display
    - Representative scanner interface
    - Kiosk mode display
    - Admin dashboard



### 3. Dynamic QR & Security Logic
*Focus: Preventing fraud and screenshots.*

- [x] **Worker Logic (Backend Complete):**
    - TOTP-based code generation (refreshes every 30s)
    - ±1 window tolerance for clock drift
- [ ] **Worker Logic (Frontend):**
    - QR code display with visual countdown
    - Visual "Live Feed" indicator (prevents using old screenshots)
- [ ] **Representative Logic:**
    - High-speed camera integration using `html5-qrcode`
    - **Local Validation:** Comparing the QR timestamp with the representative's device clock
- [ ] **Feedback System:** Haptic (vibration) and visual (green/red) feedback for scan results



### 4. Testing & Sync Mechanism
*Focus: Data integrity and Deployment.*

- [x] **Conflict Resolution (Backend):**
    - SHA256 event_id for idempotent uploads
    - Duplicate detection with configurable time window
    - Flagging system for anomalies
- [x] **Toggle Mode:** Support for alternating check-in/check-out based on last status
- [ ] **Auto-Sync (Frontend):** Background logic to detect internet and push data automatically
- [ ] **Deployment:**
    - VPS hosting for Laravel
    - Deployment of React PWA
    - Printing "How-to-Join" QR codes for easy staff onboarding

---

## 🔒 Security Measures

* **Time-Lock:** QR codes are valid for only 30 seconds (±1 window = 90s effective)
* **Device Binding:** Each worker is tied to a specific `secret_token` generated by the server
* **GPS Check:** (Optional) Location is captured during the scan to ensure they are at the job site
* **Idempotent Uploads:** SHA256-based event_id prevents duplicate records
* **Token-based Auth:** Sanctum tokens for secure API communication

---

## 📊 Database Schema

### Users Table
```
id | name | email | phone | employee_id | role | secret_token | status | timestamps
```

### Attendance Logs Table
```
id | event_id | worker_id | rep_id | kiosk_id | type | device_time | device_timezone |
sync_time | sync_status | flagged | flag_reason | latitude | longitude |
paired_log_id | work_minutes | is_late | is_early_departure | is_overtime | timestamps
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
id | name | code | secret_token | location | latitude | longitude | status | last_heartbeat_at | timestamps
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

Mode is configurable via Settings API.

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

**C. Toggle Mode:** ✅ Implemented
- `POST /api/v1/sync/logs` supports `toggle_mode: true`
- Automatically determines check-in or check-out based on last status

---

## 📈 Summary of Data Flow
1. **Morning (Online):** Rep syncs latest staff list via `GET /api/v1/sync/staff`
2. **On-Site (Offline):** Rep scans workers. Logs saved to Representative's phone (IndexedDB)
3. **Evening (Online):** App auto-detects internet and pushes logs to Laravel via `POST /api/v1/sync/logs`
4. **Admin Dashboard:** Employer views real-time reports via Reports API

---

## 📁 API Documentation

### Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/auth/register` | Register new user |
| POST | `/api/v1/auth/login` | Login with email/phone/employee_id |
| POST | `/api/v1/auth/logout` | Logout (revoke token) |
| GET | `/api/v1/auth/me` | Get current user |
| POST | `/api/v1/auth/refresh` | Refresh token |

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
| GET | `/api/v1/attendance/status` | Get current attendance status |

### TOTP
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/totp/generate` | Generate TOTP code (workers) |
| POST | `/api/v1/totp/verify` | Verify TOTP code (reps/admins) |

### Reports
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/reports/summary/{id}/daily` | Daily summary |
| GET | `/api/v1/reports/summary/{id}/weekly` | Weekly summary |
| GET | `/api/v1/reports/summary/{id}/monthly` | Monthly summary |
| GET | `/api/v1/reports/summary/{id}/yearly` | Yearly summary |
| GET | `/api/v1/reports/all/daily` | All workers daily |
| GET | `/api/v1/reports/all/weekly` | All workers weekly |
| GET | `/api/v1/reports/all/monthly` | All workers monthly |
| GET | `/api/v1/reports/all/yearly` | All workers yearly |
| GET | `/api/v1/reports/flagged` | Flagged logs |
| GET | `/api/v1/reports/logs/{id}` | Worker's logs |

### Settings (Admin)
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/settings` | List all settings |
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
