import api from '@/lib/api';
import type { LoginRequest, AuthResponse, User } from '@/types';

export async function login(data: LoginRequest): Promise<AuthResponse> {
  const response = await api.post<AuthResponse>('/auth/login', data);
  return response.data;
}

// Invite validation and acceptance
export interface InviteValidateResponse {
  valid: boolean;
  message?: string;
  user?: {
    name: string;
    email: string;
  };
}

export interface AcceptInviteRequest {
  token: string;
  password: string;
  password_confirmation: string;
}

export async function validateInvite(token: string): Promise<InviteValidateResponse> {
  const response = await api.post<InviteValidateResponse>('/invite/validate', { token });
  return response.data;
}

export async function acceptInvite(data: AcceptInviteRequest): Promise<AuthResponse> {
  const response = await api.post<AuthResponse>('/invite/accept', data);
  return response.data;
}

export async function logout(): Promise<void> {
  await api.post('/auth/logout');
}

export async function getMe(): Promise<User> {
  const response = await api.get<{ user: User }>('/auth/me');
  return response.data.user;
}

export async function refreshToken(): Promise<AuthResponse> {
  const response = await api.post<AuthResponse>('/auth/refresh');
  return response.data;
}
