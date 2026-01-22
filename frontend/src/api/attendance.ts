import api from '@/lib/api';
import type { AttendanceLog, AttendanceType } from '@/types';

export interface SelfCheckRequest {
  kiosk_code: string;
  totp_code: string;
  type?: AttendanceType;
  latitude?: number;
  longitude?: number;
}

export interface SelfCheckResponse {
  message: string;
  log: AttendanceLog;
  type: AttendanceType;
}

export interface AttendanceStatusResponse {
  checked_in: boolean;
  last_log: AttendanceLog | null;
  today_logs: AttendanceLog[];
}

export async function selfCheck(data: SelfCheckRequest): Promise<SelfCheckResponse> {
  const response = await api.post<SelfCheckResponse>('/attendance/self-check', data);
  return response.data;
}

export async function getAttendanceStatus(): Promise<AttendanceStatusResponse> {
  const response = await api.get<AttendanceStatusResponse>('/attendance/status');
  return response.data;
}
