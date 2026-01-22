import api from '@/lib/api';
import type { Staff, SyncLogsRequest, SyncLogsResponse } from '@/types';

export async function getStaffList(): Promise<Staff[]> {
  const response = await api.get<{ staff: Staff[] }>('/sync/staff');
  return response.data.staff;
}

export async function syncLogs(data: SyncLogsRequest): Promise<SyncLogsResponse> {
  const response = await api.post<SyncLogsResponse>('/sync/logs', data);
  return response.data;
}

export async function getServerTime(): Promise<{ server_time: string; timezone: string }> {
  const response = await api.get<{ server_time: string; timezone: string }>('/time');
  return response.data;
}
