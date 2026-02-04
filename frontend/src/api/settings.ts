import api from '@/lib/api';
import type { Settings } from '@/types';

// Settings
export const getSettings = async (): Promise<{ settings: Settings }> => {
  const response = await api.get('/settings');
  return response.data;
};

export const getSettingsGroup = async (group: string): Promise<{ group: string; settings: Record<string, { key: string; value: unknown; type: string; description: string }> }> => {
  const response = await api.get(`/settings/group/${group}`);
  return response.data;
};

export const updateSetting = async (key: string, value: unknown): Promise<{ message: string; key: string; value: unknown }> => {
  const response = await api.put(`/settings/${key}`, { value });
  return response.data;
};

export const updateBulkSettings = async (settings: Array<{ key: string; value: unknown }>): Promise<{
  message: string;
  updated_count: number;
  error_count: number;
  updated: Array<{ key: string; value: unknown }>;
  errors: Array<{ key: string; error: string }>;
}> => {
  const response = await api.put('/settings/bulk', { settings });
  return response.data;
};

export const getWorkHours = async (): Promise<{
  config: { work_start: string; work_end: string; lunch_start: string; lunch_end: string };
  working_days: string[];
  shifts_enabled: boolean;
  shifts: Record<string, { name: string; start: string; end: string }>;
  default_shift: string;
}> => {
  const response = await api.get('/settings/work-hours');
  return response.data;
};

export const getAttendanceMode = async (): Promise<{
  attendance_mode: 'representative' | 'kiosk';
  description: string;
}> => {
  const response = await api.get('/settings/attendance-mode');
  return response.data;
};

export const updateAttendanceMode = async (mode: 'representative' | 'kiosk'): Promise<{
  message: string;
  attendance_mode: 'representative' | 'kiosk';
  description: string;
}> => {
  const response = await api.put('/settings/config/attendance-mode', { attendance_mode: mode });
  return response.data;
};

export const updateWorkingDays = async (workingDays: string[]): Promise<{
  message: string;
  working_days: string[];
  weekend_days: string[];
}> => {
  const response = await api.put('/settings/working-days', { working_days: workingDays });
  return response.data;
};

export interface ShiftData {
  name: string;
  code: string;
  start_time: string;
  end_time: string;
  break_minutes: number;
}

export const updateShifts = async (shifts: ShiftData[]): Promise<{
  message: string;
  shifts: ShiftData[];
}> => {
  const response = await api.put('/settings/config/shifts', { shifts });
  return response.data;
};
