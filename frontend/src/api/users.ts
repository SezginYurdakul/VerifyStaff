import api from '@/lib/api';
import type { User, UserRole, UserStatus } from '@/types';

export interface UsersListResponse {
  users: User[];
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
}

export interface CreateUserRequest {
  name: string;
  email: string;
  phone?: string;
  employee_id?: string;
  department_id?: number | null;
  role: UserRole;
}

export interface UpdateUserRequest {
  name?: string;
  email?: string;
  phone?: string | null;
  employee_id?: string | null;
  department_id?: number | null;
  role?: UserRole;
  status?: UserStatus;
}

export async function getUsers(params?: {
  page?: number;
  per_page?: number;
  role?: UserRole;
  status?: UserStatus;
  department_id?: number;
  trashed?: boolean;
}): Promise<UsersListResponse> {
  const response = await api.get<UsersListResponse>('/users', { params });
  return response.data;
}

export async function getUser(id: number): Promise<{ user: User }> {
  const response = await api.get<{ user: User }>(`/users/${id}`);
  return response.data;
}

export async function createUser(data: CreateUserRequest): Promise<{ message: string; user: User }> {
  const response = await api.post<{ message: string; user: User }>('/users', data);
  return response.data;
}

export async function updateUser(id: number, data: UpdateUserRequest): Promise<{ message: string; user: User }> {
  const response = await api.put<{ message: string; user: User }>(`/users/${id}`, data);
  return response.data;
}

export async function deleteUser(id: number): Promise<{ message: string }> {
  const response = await api.delete<{ message: string }>(`/users/${id}`);
  return response.data;
}

export async function resendInvite(id: number): Promise<{ message: string }> {
  const response = await api.post<{ message: string }>(`/users/${id}/resend-invite`);
  return response.data;
}

export async function restoreUser(id: number): Promise<{ message: string; user: User }> {
  const response = await api.post<{ message: string; user: User }>(`/users/${id}/restore`);
  return response.data;
}

export async function forceDeleteUser(id: number): Promise<{ message: string }> {
  const response = await api.delete<{ message: string }>(`/users/${id}/force`);
  return response.data;
}
