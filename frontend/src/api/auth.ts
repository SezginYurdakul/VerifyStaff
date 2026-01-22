import api from '@/lib/api';
import type { LoginRequest, RegisterRequest, AuthResponse, User } from '@/types';

export async function login(data: LoginRequest): Promise<AuthResponse> {
  const response = await api.post<AuthResponse>('/auth/login', data);
  return response.data;
}

export async function register(data: RegisterRequest): Promise<AuthResponse> {
  const response = await api.post<AuthResponse>('/auth/register', data);
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
