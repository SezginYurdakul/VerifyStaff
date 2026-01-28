import api from '@/lib/api';
import type { Kiosk } from '@/types';

// Get kiosk QR code (public endpoint - no auth needed)
export async function getKioskCode(kioskCode: string): Promise<{
  totp_code: string;
  kiosk_code: string;
  kiosk_name: string;
  expires_at: string;
  remaining_seconds: number;
  refresh_seconds: number;
}> {
  const response = await api.get(`/kiosk/${kioskCode}/code`);
  return response.data;
}

// Admin: List all kiosks
export async function getKiosks(): Promise<Kiosk[]> {
  const response = await api.get<{ kiosks: Kiosk[] }>('/kiosks');
  return response.data.kiosks;
}

// Admin: Create kiosk
export async function createKiosk(data: {
  name: string;
  location?: string;
  latitude?: number;
  longitude?: number;
}): Promise<Kiosk> {
  const response = await api.post<{ kiosk: Kiosk }>('/kiosks', data);
  return response.data.kiosk;
}

// Admin: Get kiosk by code
export async function getKiosk(kioskCode: string): Promise<Kiosk> {
  const response = await api.get<{ kiosk: Kiosk }>(`/kiosks/${kioskCode}`);
  return response.data.kiosk;
}

// Admin: Update kiosk
export async function updateKiosk(
  kioskCode: string,
  data: Partial<Pick<Kiosk, 'name' | 'location' | 'latitude' | 'longitude' | 'status'>>
): Promise<Kiosk> {
  const response = await api.put<{ kiosk: Kiosk }>(`/kiosks/${kioskCode}`, data);
  return response.data.kiosk;
}

// Admin: Regenerate kiosk token
export async function regenerateKioskToken(kioskCode: string): Promise<Kiosk> {
  const response = await api.post<{ kiosk: Kiosk }>(`/kiosks/${kioskCode}/regenerate-token`);
  return response.data.kiosk;
}

// Worker: Sync offline kiosk attendance logs
export interface OfflineKioskSyncRequest {
  logs: {
    kiosk_code: string;
    device_time: string;
    device_timezone: string;
    event_id: string;
    scanned_totp?: string | null;
  }[];
}

export interface OfflineKioskSyncResponse {
  message: string;
  server_time: string;
  synced_count: number;
  duplicate_count: number;
  error_count: number;
  synced: { event_id: string; type: string }[];
  duplicates: string[];
  errors: { event_id: string; reason: string }[];
}

export async function syncOfflineKioskLogs(
  data: OfflineKioskSyncRequest
): Promise<OfflineKioskSyncResponse> {
  const response = await api.post<OfflineKioskSyncResponse>(
    '/attendance/sync-offline',
    data
  );
  return response.data;
}

// Worker: Check-in/check-out via kiosk QR code
export async function kioskCheckIn(
  kioskCode: string,
  totpCode: string
): Promise<{
  action: "check_in" | "check_out";
  time: string;
}> {
  const deviceTime = new Date().toISOString();
  const deviceTimezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

  const response = await api.post<{
    message: string;
    type: "in" | "out";
    device_time: string;
  }>("/attendance/self-check", {
    device_time: deviceTime,
    device_timezone: deviceTimezone,
    kiosk_code: kioskCode,
    kiosk_totp: totpCode,
  });

  return {
    action: response.data.type === "in" ? "check_in" : "check_out",
    time: response.data.device_time,
  };
}
