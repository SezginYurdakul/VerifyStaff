import api from '@/lib/api';

export interface DashboardOverview {
    date: string;
    active_workers: number;
    today: {
        checkins: number;
        checkouts: number;
        currently_working: number;
        attendance_rate: number;
        missing_checkouts: number;
    };
    this_week: {
        total_hours: number;
        overtime_hours: number;
        unique_workers: number;
        late_arrivals: number;
    };
    this_month: {
        total_hours: number;
        overtime_hours: number;
        unique_workers: number;
        days_with_activity: number;
    };
    alerts: {
        flagged_records: number;
        missing_checkouts_today: number;
    };
}

export interface TrendDataPoint {
    date: string;
    day: string;
    checkins: number;
    checkouts: number;
    total_hours: number;
    late_arrivals: number;
    early_departures: number;
    attendance_rate: number;
}

export interface DashboardTrends {
    period: {
        start: string;
        end: string;
        days: number;
    };
    averages: {
        daily_checkins: number;
        daily_hours: number;
        attendance_rate: number;
    };
    data: TrendDataPoint[];
}

export interface AnomalyItem {
    type: string;
    severity: 'high' | 'medium' | 'low';
    log_id: number;
    worker: {
        id: number;
        name: string;
    } | null;
    reason?: string;
    time?: string;
    checkin_time?: string;
}

export interface InactiveWorker {
    id: number;
    name: string;
    employee_id: string;
}

export interface DashboardAnomalies {
    summary: {
        flagged_count: number;
        missing_checkouts_count: number;
        late_arrivals_this_week: number;
        inactive_workers: number;
    };
    flagged: AnomalyItem[];
    missing_checkouts: AnomalyItem[];
    late_arrivals: AnomalyItem[];
    inactive_workers: InactiveWorker[];
}

export async function getDashboardOverview(): Promise<DashboardOverview> {
    const response = await api.get('/dashboard/overview');
    return response.data;
}

export async function getDashboardTrends(days: number = 7): Promise<DashboardTrends> {
    const response = await api.get('/dashboard/trends', { params: { days } });
    return response.data;
}

export async function getDashboardAnomalies(limit: number = 10): Promise<DashboardAnomalies> {
    const response = await api.get('/dashboard/anomalies', { params: { limit } });
    return response.data;
}
