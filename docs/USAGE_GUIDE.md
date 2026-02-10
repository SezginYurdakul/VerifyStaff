# VerifyStaff - User Guide

**Live Demo:** [https://verifystaff.sezginyurdakul.com](https://verifystaff.sezginyurdakul.com)

VerifyStaff is an offline-first attendance tracking system designed for organizations that need reliable time tracking even in areas with poor internet connectivity.

---

## Table of Contents

1. [Demo Accounts](#demo-accounts)
2. [User Roles](#user-roles)
3. [Getting Started](#getting-started)
4. [Attendance Modes](#attendance-modes)
5. [Admin Features](#admin-features)
6. [Representative Features](#representative-features)
7. [Worker Features](#worker-features)
8. [Kiosk Mode](#kiosk-mode)
9. [Offline Support](#offline-support)

---

## Demo Accounts

You can test the application using these pre-configured accounts:

| Role | Email | Password |
|------|-------|----------|
| **Admin** | admin@verifystaff.com | password123 |
| **Representative** | rep@verifystaff.com | password123 |
| **Worker** | emma@example.com | password123 |

Feel free to create, edit, or delete users to test the full functionality.

---

## User Roles

### Admin
- Full system access
- Manage users (create, edit, delete, restore)
- Manage departments
- Configure system settings
- View all reports and analytics
- Access dashboard with anomaly alerts

### Representative
- Scan worker QR codes to record attendance
- Sync attendance logs to server
- Works offline with automatic sync when online
- View their own scan history

### Worker
- Display personal QR code for check-in/check-out
- View personal attendance history
- Access weekly and monthly summaries
- Use kiosk mode for self-service check-in

---

## Getting Started

### Creating a New User

1. Log in as **Admin**
2. Navigate to **Users** from the menu
3. Click **Create User**
4. Fill in the user details:
   - Name
   - Email (required for login)
   - Phone (optional)
   - Employee ID (optional)
   - Department (optional)
   - Role (Admin, Representative, or Worker)
5. Click **Create & Send Invite**

The user will receive an email from `sezginyurdakul@gmail.com` with a link to set their password.

### Setting Password via Email

1. New users receive an invitation email
2. Click the **Set Your Password** button in the email
3. You'll be redirected to the password setup page
4. Enter and confirm your new password
5. After setting the password, you can log in immediately

---

## Attendance Modes

VerifyStaff supports two attendance tracking modes:

### Representative Mode
- A representative uses their phone to scan worker QR codes
- Best for: Construction sites, warehouses, field teams
- The representative's device records time and location
- Works completely offline

### Kiosk Mode
- Workers scan their QR codes on a shared tablet/kiosk
- Best for: Office entrances, factory gates
- Self-service check-in/check-out
- Requires TOTP verification for security

To switch modes: **Settings > Attendance > Attendance Mode**

---

## Admin Features

### Dashboard
- Overview of today's attendance statistics
- Anomaly alerts (late arrivals, early departures, missing checkouts, inactive workers)
- Click on anomaly categories to see detailed lists

### User Management
Navigate to **Users** to:
- View all users with filtering by role, status, and department
- Create new users (sends email invitation)
- Edit existing users
- Delete users (soft delete - can be restored)
- View deleted users by clicking **Show Deleted**
- Restore or permanently delete users
- Resend invitation emails
- View and print worker QR codes

### Department Management
Navigate to **Departments** to:
- Create departments with custom shift schedules
- Set department-specific:
  - Shift start/end times
  - Late arrival threshold
  - Early departure threshold
  - Working days
- Workers inherit attendance rules from their department

### Settings

#### General Tab
- Company name
- Timezone configuration
- Auto checkout settings

#### Attendance Tab
- Switch between Representative and Kiosk modes
- Configure working days

#### Shifts Tab
- Enable/disable multiple shifts
- Define default work hours
- Set late arrival and early departure thresholds

#### Kiosks Tab (for Kiosk mode)
- Create and manage kiosk devices
- Generate kiosk display URLs
- Configure kiosk locations

### Reports
Navigate to **Reports** to:
- View attendance data by date range
- Filter by department
- See individual worker details
- Export data

---

## Representative Features

### Scanning Workers

1. Log in as a Representative
2. Click **Scan** in the navigation
3. Point your camera at a worker's QR code
4. The system automatically:
   - Identifies the worker
   - Determines if it's a check-in or check-out
   - Records the timestamp and location
   - Shows confirmation with worker details

### Check-in vs Check-out
- First scan of the day = **Check-in**
- Subsequent scans = **Check-out**
- The system pairs check-ins with check-outs automatically

### Offline Scanning
- Scans are stored locally when offline
- A sync indicator shows pending logs count
- Logs sync automatically when connection is restored
- You can also manually sync from the scan page

---

## Worker Features

### QR Code Display

1. Log in as a Worker
2. Your QR code is displayed on the home screen
3. The code contains your unique identifier
4. Show this to a representative or scan at a kiosk

### Personal Dashboard

Workers can view:
- Current status (checked in/out)
- Today's work hours
- This week's summary with navigation to past weeks
- This month's summary with navigation to past months
- Click **View Details** to see daily breakdown

### Kiosk Self-Service

When kiosk mode is enabled:
1. Navigate to **Kiosk Scan**
2. Scan the kiosk's QR code
3. Enter the TOTP code displayed on the kiosk
4. Your check-in/check-out is recorded

---

## Kiosk Mode

### Setting Up a Kiosk

1. As Admin, go to **Settings > Kiosks**
2. Click **Create Kiosk**
3. Enter kiosk details (name, location)
4. Copy the generated display URL
5. Open the URL on the kiosk tablet/display

### Kiosk Display

The kiosk display shows:
- Large QR code for workers to scan
- Current TOTP code (changes every 30 seconds)
- Countdown timer
- Works offline with cached codes

### Worker Check-in at Kiosk

1. Worker opens the VerifyStaff app
2. Goes to **Kiosk Scan**
3. Scans the kiosk QR code
4. Enters the TOTP code shown on kiosk display
5. Check-in/check-out is recorded

---

## Offline Support

VerifyStaff is designed to work without internet:

### Progressive Web App (PWA)
- Install on your phone's home screen
- Opens instantly like a native app
- Works completely offline

### Offline Features

| Feature | Offline Support |
|---------|-----------------|
| Representative scanning | Yes - syncs when online |
| Worker QR display | Yes - cached |
| Kiosk display | Yes - cached TOTP |
| Kiosk self-check-in | Yes - syncs when online |
| View cached data | Yes |
| Real-time reports | No - requires connection |

### Sync Indicators
- Green badge: All synced
- Yellow badge with number: Pending logs to sync
- Offline banner: No internet connection

### Data Integrity
- All offline actions are timestamped with device time
- Flagged records indicate potential issues (e.g., "Offline kiosk sync - TOTP not verified")
- Admins can review flagged records in reports

---

## Tips & Best Practices

1. **For Admins:**
   - Set up departments before adding workers
   - Review anomaly alerts daily
   - Use soft delete instead of permanent delete

2. **For Representatives:**
   - Ensure GPS is enabled for location tracking
   - Check sync status before ending shift
   - Use manual sync if automatic sync fails

3. **For Workers:**
   - Install the app as PWA for better experience
   - Keep the app updated
   - Contact admin if QR code doesn't work

---

## Troubleshooting

### Email not received
- Check spam folder
- Ask admin to resend invitation
- Verify email address is correct

### QR code not scanning
- Ensure good lighting
- Clean camera lens
- Hold steady at appropriate distance
- Try refreshing the QR code page

### Sync not working
- Check internet connection
- Try manual sync
- Clear app cache and retry
- Contact admin if issue persists

### Wrong attendance mode
- Only admins can change the mode
- Go to Settings > Attendance > Attendance Mode

---

## Support

For issues or feedback, please contact the system administrator or create an issue at the project repository.
