import api from '@/lib/api';
import type { Kiosk } from '@/types';

// Get kiosk QR code (public endpoint - no auth needed)
export async function getKioskCode(kioskCode: string): Promise<{
  code: string;
  kiosk_code: string;
  expires_at: string;
  remaining_seconds: number;
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
