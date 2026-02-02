import api from '@/lib/api';

// ==================== Types ====================

export interface DailySummary {
  checkin_time: string | null;
  checkout_time: string | null;
  work_minutes: number;
  overtime_minutes: number;
  is_late: boolean;
  is_early_departure: boolean;
  status: 'complete' | 'in_progress' | 'absent';
}

export interface WeeklySummary {
  total_minutes: number;
  regular_minutes: number;
  overtime_minutes: number;
  days_worked: number;
  late_count: number;
  early_departure_count: number;
  average_checkin_time: string | null;
  average_checkout_time: string | null;
}

export interface WorkerInfo {
  id: number;
  name: string;
  employee_id?: string;
}

// ==================== Single Worker Responses ====================

export interface DailySummaryResponse {
  worker: WorkerInfo;
  period: 'daily';
  date: string;
  summary: DailySummary;
}

export interface WeeklySummaryResponse {
  worker: WorkerInfo;
  period: 'weekly';
  week_start: string;
  week_end: string;
  summary: WeeklySummary;
  calculated_at: string | null;
}

export interface MonthlySummaryResponse {
  worker: WorkerInfo;
  period: 'monthly';
  month: string;
  month_start: string;
  month_end: string;
  summary: WeeklySummary;
  calculated_at: string | null;
}

export interface YearlySummaryResponse {
  worker: WorkerInfo;
  period: 'yearly';
  year: number;
  year_start: string;
  year_end: string;
  summary: WeeklySummary;
  calculated_at: string | null;
}

// ==================== All Workers Responses ====================

export interface WorkerDailySummary {
  id: number;
  name: string;
  employee_id: string | null;
  total_hours: number;
  total_minutes: number;
  regular_hours: number;
  overtime_hours: number;
  formatted_time: string;
  late_arrivals: number;
  early_departures: number;
}

export interface WorkerWeeklySummary {
  id: number;
  name: string;
  employee_id: string | null;
  total_hours: number;
  total_minutes: number;
  formatted_time: string;
  days_worked: number;
  days_absent: number;
  late_arrivals: number;
  early_departures: number;
}

export interface WorkerMonthlySummary {
  id: number;
  name: string;
  employee_id: string | null;
  total_hours: number;
  total_minutes: number;
  formatted_time: string;
  days_worked: number;
  days_absent: number;
  late_arrivals: number;
  early_departures: number;
  overtime_hours: number;
}

export interface AllWorkersDailyResponse {
  period: 'daily';
  date: string;
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
  workers: WorkerDailySummary[];
}

export interface AllWorkersWeeklyResponse {
  period: 'weekly';
  week_start: string;
  week_end: string;
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
  workers: WorkerWeeklySummary[];
}

export interface AllWorkersMonthlySummary {
  period: 'monthly';
  month: string;
  month_start: string;
  month_end: string;
  total: number;
  per_page: number;
  current_page: number;
  last_page: number;
  workers: WorkerMonthlySummary[];
}

export interface AttendanceLog {
  id: number;
  type: 'in' | 'out';
  device_time: string;
  flagged: boolean;
  flag_reason: string | null;
  is_late: boolean;
  is_early_departure: boolean;
  work_minutes: number | null;
}

export interface DayLogs {
  date: string;
  summary: DailySummary;
  logs: AttendanceLog[];
}

export interface WorkerLogsResponse {
  worker: WorkerInfo;
  period: {
    from: string;
    to: string;
  };
  summary: {
    total_minutes: number;
    total_days: number;
    days_worked: number;
    days_absent: number;
  };
  total_days: number;
  total_logs: number;
  days: DayLogs[];
}

// ==================== Single Worker API ====================

export async function getWorkerDailySummary(
  workerId: number,
  date?: string
): Promise<DailySummaryResponse> {
  const params = date ? { date } : {};
  const response = await api.get<DailySummaryResponse>(
    `/reports/summary/${workerId}/daily`,
    { params }
  );
  return response.data;
}

export async function getWorkerWeeklySummary(
  workerId: number,
  date?: string
): Promise<WeeklySummaryResponse> {
  const params = date ? { date } : {};
  const response = await api.get<WeeklySummaryResponse>(
    `/reports/summary/${workerId}/weekly`,
    { params }
  );
  return response.data;
}

export async function getWorkerMonthlySummary(
  workerId: number,
  month?: string
): Promise<MonthlySummaryResponse> {
  const params = month ? { month } : {};
  const response = await api.get<MonthlySummaryResponse>(
    `/reports/summary/${workerId}/monthly`,
    { params }
  );
  return response.data;
}

export async function getWorkerYearlySummary(
  workerId: number,
  year?: number
): Promise<YearlySummaryResponse> {
  const params = year ? { year } : {};
  const response = await api.get<YearlySummaryResponse>(
    `/reports/summary/${workerId}/yearly`,
    { params }
  );
  return response.data;
}

export async function getWorkerLogs(
  workerId: number,
  from?: string,
  to?: string
): Promise<WorkerLogsResponse> {
  const params: Record<string, string> = {};
  if (from) params.from = from;
  if (to) params.to = to;
  const response = await api.get<WorkerLogsResponse>(
    `/reports/logs/${workerId}`,
    { params }
  );
  return response.data;
}

// ==================== All Workers API ====================

export async function getAllWorkersDailySummary(
  date?: string,
  page?: number,
  perPage?: number
): Promise<AllWorkersDailyResponse> {
  const params: Record<string, string | number> = {};
  if (date) params.date = date;
  if (page) params.page = page;
  if (perPage) params.per_page = perPage;
  const response = await api.get<AllWorkersDailyResponse>(
    '/reports/all/daily',
    { params }
  );
  return response.data;
}

export async function getAllWorkersWeeklySummary(
  date?: string,
  page?: number,
  perPage?: number
): Promise<AllWorkersWeeklyResponse> {
  const params: Record<string, string | number> = {};
  if (date) params.date = date;
  if (page) params.page = page;
  if (perPage) params.per_page = perPage;
  const response = await api.get<AllWorkersWeeklyResponse>(
    '/reports/all/weekly',
    { params }
  );
  return response.data;
}

export async function getAllWorkersMonthlySummary(
  month?: string,
  page?: number,
  perPage?: number
): Promise<AllWorkersMonthlySummary> {
  const params: Record<string, string | number> = {};
  if (month) params.month = month;
  if (page) params.page = page;
  if (perPage) params.per_page = perPage;
  const response = await api.get<AllWorkersMonthlySummary>(
    '/reports/all/monthly',
    { params }
  );
  return response.data;
}
