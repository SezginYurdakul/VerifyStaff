import api from '@/lib/api';
import type { TotpGenerateResponse, TotpVerifyRequest, TotpVerifyResponse } from '@/types';

export async function generateTotpCode(): Promise<TotpGenerateResponse> {
  const response = await api.get<TotpGenerateResponse>('/totp/generate');
  return response.data;
}

export async function verifyTotpCode(data: TotpVerifyRequest): Promise<TotpVerifyResponse> {
  const response = await api.post<TotpVerifyResponse>('/totp/verify', data);
  return response.data;
}
