// User types
export type UserRole = 'admin' | 'representative' | 'worker';
export type UserStatus = 'active' | 'inactive' | 'suspended';

export interface User {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  employee_id: string | null;
  role: UserRole;
  status: UserStatus;
  secret_token: string | null;
  created_at: string;
  updated_at: string;
}

// Auth types
export interface LoginRequest {
  identifier: string;
  password: string;
}

export interface RegisterRequest {
  name: string;
  email?: string;
  phone?: string;
  employee_id?: string;
  password: string;
  password_confirmation: string;
  role?: UserRole;
}

export interface AuthResponse {
  message: string;
  user: User;
  token: string;
  token_type: string;
}

// Attendance types
export type AttendanceType = 'in' | 'out';
export type SyncStatus = 'pending' | 'syncing' | 'synced' | 'failed';

export interface AttendanceLog {
  id?: number;
  event_id: string;
  worker_id: number;
  rep_id: number | null;
  kiosk_id: number | null;
  type: AttendanceType;
  device_time: string;
  device_timezone: string;
  sync_time: string | null;
  sync_status: SyncStatus;
  sync_attempt: number;
  offline_duration_seconds: number;
  flagged: boolean;
  flag_reason: string | null;
  latitude: number | null;
  longitude: number | null;
  work_minutes: number | null;
  is_late: boolean | null;
  is_early_departure: boolean | null;
  is_overtime: boolean | null;
}

// Staff (for offline validation)
export interface Staff {
  id: number;
  name: string;
  employee_id: string | null;
  secret_token: string;
  status: UserStatus;
}

// TOTP types
export interface TotpGenerateResponse {
  code: string;
  expires_at: string;
  remaining_seconds: number;
}

export interface TotpVerifyRequest {
  worker_id: number;
  code: string;
}

export interface TotpVerifyResponse {
  valid: boolean;
  worker?: Staff;
  message: string;
}

// Kiosk types
export type KioskStatus = 'active' | 'inactive' | 'maintenance';

export interface Kiosk {
  id: number;
  name: string;
  code: string;
  location: string | null;
  latitude: number | null;
  longitude: number | null;
  status: KioskStatus;
  last_heartbeat_at: string | null;
  created_at: string;
  updated_at: string;
}

// Settings types
export interface Setting {
  id: number;
  key: string;
  group: string;
  value: string;
  type: 'string' | 'integer' | 'boolean' | 'json' | 'time';
  description: string | null;
}

export interface WorkHours {
  work_start_time: string;
  work_end_time: string;
  break_duration_minutes: number;
  regular_work_minutes: number;
  late_threshold_minutes: number;
  early_departure_threshold_minutes: number;
  overtime_threshold_minutes: number;
}

// Report types
export interface WorkSummary {
  worker_id: number;
  worker_name: string;
  period_type: 'daily' | 'weekly' | 'monthly' | 'yearly';
  period_start: string;
  period_end: string;
  total_minutes: number;
  regular_minutes: number;
  overtime_minutes: number;
  days_worked: number;
  days_absent: number;
  late_arrivals: number;
  early_departures: number;
  missing_checkouts: number;
  missing_checkins: number;
}

// API Response types
export interface ApiResponse<T> {
  message?: string;
  data?: T;
}

export interface ApiError {
  message: string;
  error?: {
    code: string;
    details?: Record<string, string[]>;
  };
}

// Sync types
export interface SyncLogsRequest {
  logs: Omit<AttendanceLog, 'id' | 'sync_time' | 'sync_status'>[];
  toggle_mode?: boolean;
}

export interface SyncLogsResponse {
  message: string;
  processed: number;
  duplicates: number;
  failed: number;
  results: {
    event_id: string;
    status: 'created' | 'duplicate' | 'failed';
    message?: string;
  }[];
}
