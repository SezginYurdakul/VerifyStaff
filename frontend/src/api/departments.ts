import api from '@/lib/api';
import type { Department } from '@/types';

export interface DepartmentsListResponse {
  departments: Department[];
  total: number;
}

export interface CreateDepartmentRequest {
  name: string;
  code: string;
  shift_start: string;
  shift_end: string;
  late_threshold_minutes: number;
  early_departure_threshold_minutes: number;
  regular_work_minutes: number;
  working_days?: string[];
  description?: string;
  is_active?: boolean;
}

export interface UpdateDepartmentRequest {
  name?: string;
  code?: string;
  shift_start?: string;
  shift_end?: string;
  late_threshold_minutes?: number;
  early_departure_threshold_minutes?: number;
  regular_work_minutes?: number;
  working_days?: string[];
  description?: string;
  is_active?: boolean;
}

export async function getDepartments(params?: {
  is_active?: boolean;
}): Promise<DepartmentsListResponse> {
  const response = await api.get<DepartmentsListResponse>('/departments', { params });
  return response.data;
}

export async function getDepartment(id: number): Promise<{ department: Department }> {
  const response = await api.get<{ department: Department }>(`/departments/${id}`);
  return response.data;
}

export async function createDepartment(data: CreateDepartmentRequest): Promise<{ message: string; department: Department }> {
  const response = await api.post<{ message: string; department: Department }>('/departments', data);
  return response.data;
}

export async function updateDepartment(id: number, data: UpdateDepartmentRequest): Promise<{ message: string; department: Department }> {
  const response = await api.put<{ message: string; department: Department }>(`/departments/${id}`, data);
  return response.data;
}

export async function deleteDepartment(id: number): Promise<{ message: string }> {
  const response = await api.delete<{ message: string }>(`/departments/${id}`);
  return response.data;
}
