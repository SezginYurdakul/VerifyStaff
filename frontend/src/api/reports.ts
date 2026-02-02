import api from '@/lib/api';

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

export interface DailySummaryResponse {
  worker: { id: number; name: string };
  period: 'daily';
  date: string;
  summary: DailySummary;
}

export interface WeeklySummaryResponse {
  worker: { id: number; name: string };
  period: 'weekly';
  week_start: string;
  week_end: string;
  summary: WeeklySummary;
  calculated_at: string | null;
}

export interface MonthlySummaryResponse {
  worker: { id: number; name: string };
  period: 'monthly';
  month: string;
  month_start: string;
  month_end: string;
  summary: WeeklySummary;
  calculated_at: string | null;
}

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
