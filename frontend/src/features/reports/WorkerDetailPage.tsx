import { useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import {
    getWorkerLogs,
    getWorkerWeeklySummary,
    getWorkerMonthlySummary,
    getWorkerYearlySummary,
    type DayLogs,
    type AttendanceLog,
} from '@/api/reports';
import {
    ArrowLeft,
    Calendar,
    Clock,
    User,
    Timer,
    TrendingUp,
    AlertTriangle,
    LogIn,
    LogOut,
    Flag,
    ChevronDown,
    ChevronRight,
} from 'lucide-react';

type TabType = 'logs' | 'weekly' | 'monthly' | 'yearly';

function formatMinutes(minutes: number): string {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    if (hours === 0) return `${mins}m`;
    if (mins === 0) return `${hours}h`;
    return `${hours}h ${mins}m`;
}

function formatTime(timeString: string | null): string {
    if (!timeString) return '-';
    try {
        const date = new Date(timeString);
        return date.toLocaleTimeString('en-US', {
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return '-';
    }
}

function formatDate(dateString: string): string {
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
    } catch {
        return dateString;
    }
}

function formatShortDate(dateString: string): string {
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', {
            weekday: 'short',
            day: 'numeric',
            month: 'short',
        });
    } catch {
        return dateString;
    }
}

function LogEntry({ log }: { log: AttendanceLog }) {
    return (
        <div className={`flex items-center gap-3 p-3 rounded-lg ${
            log.flagged ? 'bg-red-50 border border-red-200' : 'bg-gray-50'
        }`}>
            <div className={`p-2 rounded-full ${
                log.type === 'in' ? 'bg-green-100' : 'bg-red-100'
            }`}>
                {log.type === 'in' ? (
                    <LogIn className={`w-4 h-4 ${log.is_late ? 'text-yellow-600' : 'text-green-600'}`} />
                ) : (
                    <LogOut className={`w-4 h-4 ${log.is_early_departure ? 'text-orange-600' : 'text-red-600'}`} />
                )}
            </div>
            <div className="flex-1">
                <div className="flex items-center gap-2">
                    <span className="font-medium text-gray-900">
                        {log.type === 'in' ? 'Check-in' : 'Check-out'}
                    </span>
                    <span className="text-gray-500">{formatTime(log.device_time)}</span>
                    {log.is_late && (
                        <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                            Late
                        </span>
                    )}
                    {log.is_early_departure && (
                        <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                            Early
                        </span>
                    )}
                    {log.flagged && (
                        <span className="px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 flex items-center gap-1">
                            <Flag className="w-3 h-3" />
                            Flagged
                        </span>
                    )}
                </div>
                {log.work_minutes !== null && log.work_minutes > 0 && (
                    <p className="text-sm text-gray-500">
                        Work time: {formatMinutes(log.work_minutes)}
                    </p>
                )}
                {log.flag_reason && (
                    <p className="text-sm text-red-600 mt-1">{log.flag_reason}</p>
                )}
            </div>
        </div>
    );
}

function DayCard({ day, isExpanded, onToggle }: {
    day: DayLogs;
    isExpanded: boolean;
    onToggle: () => void;
}) {
    const statusColors = {
        complete: 'bg-green-100 text-green-800 border-green-200',
        in_progress: 'bg-blue-100 text-blue-800 border-blue-200',
        absent: 'bg-gray-100 text-gray-600 border-gray-200',
    };

    return (
        <Card className="overflow-hidden">
            <button
                onClick={onToggle}
                className="w-full flex items-center justify-between p-4 hover:bg-gray-50 transition-colors"
            >
                <div className="flex items-center gap-4">
                    <div className="flex items-center gap-2">
                        {isExpanded ? (
                            <ChevronDown className="w-5 h-5 text-gray-400" />
                        ) : (
                            <ChevronRight className="w-5 h-5 text-gray-400" />
                        )}
                        <Calendar className="w-5 h-5 text-gray-400" />
                    </div>
                    <div className="text-left">
                        <p className="font-medium text-gray-900">{formatShortDate(day.date)}</p>
                        <p className="text-sm text-gray-500">
                            {day.logs.length} log{day.logs.length !== 1 ? 's' : ''}
                        </p>
                    </div>
                </div>
                <div className="flex items-center gap-3">
                    {day.summary.work_minutes > 0 && (
                        <div className="flex items-center gap-1 text-gray-600">
                            <Timer className="w-4 h-4" />
                            <span className="text-sm font-medium">
                                {formatMinutes(day.summary.work_minutes)}
                            </span>
                        </div>
                    )}
                    <span className={`px-3 py-1 rounded-full text-xs font-medium border ${statusColors[day.summary.status]}`}>
                        {day.summary.status === 'complete' && 'Complete'}
                        {day.summary.status === 'in_progress' && 'Working'}
                        {day.summary.status === 'absent' && 'Absent'}
                    </span>
                </div>
            </button>
            {isExpanded && day.logs.length > 0 && (
                <div className="px-4 pb-4 pt-2 border-t border-gray-100">
                    <div className="grid gap-2 ml-7">
                        {day.logs.map((log) => (
                            <LogEntry key={log.id} log={log} />
                        ))}
                    </div>
                </div>
            )}
        </Card>
    );
}

function SummaryCard({
    title,
    icon: Icon,
    iconColor,
    children,
}: {
    title: string;
    icon: React.ElementType;
    iconColor: string;
    children: React.ReactNode;
}) {
    return (
        <Card>
            <div className="flex items-center gap-3 mb-4">
                <div className={`p-2 rounded-lg ${iconColor}`}>
                    <Icon className="w-5 h-5" />
                </div>
                <h3 className="font-semibold text-gray-900">{title}</h3>
            </div>
            {children}
        </Card>
    );
}

function StatItem({ label, value, subValue }: { label: string; value: string | number; subValue?: string }) {
    return (
        <div className="flex justify-between items-center py-2 border-b border-gray-100 last:border-0">
            <span className="text-gray-600">{label}</span>
            <div className="text-right">
                <span className="font-medium text-gray-900">{value}</span>
                {subValue && <span className="text-sm text-gray-500 ml-2">{subValue}</span>}
            </div>
        </div>
    );
}

export default function WorkerDetailPage() {
    const { workerId } = useParams<{ workerId: string }>();
    const navigate = useNavigate();
    const [activeTab, setActiveTab] = useState<TabType>('logs');
    const [expandedDays, setExpandedDays] = useState<Set<string>>(new Set());

    const id = parseInt(workerId || '0', 10);

    // Get date range for logs (last 30 days)
    const today = new Date();
    const thirtyDaysAgo = new Date(today);
    thirtyDaysAgo.setDate(today.getDate() - 30);

    const { data: logsData, isLoading: logsLoading } = useQuery({
        queryKey: ['worker', id, 'logs'],
        queryFn: () => getWorkerLogs(id, thirtyDaysAgo.toISOString().split('T')[0], today.toISOString().split('T')[0]),
        enabled: !!id,
    });

    const { data: weeklyData, isLoading: weeklyLoading } = useQuery({
        queryKey: ['worker', id, 'weekly'],
        queryFn: () => getWorkerWeeklySummary(id),
        enabled: !!id && activeTab === 'weekly',
    });

    const { data: monthlyData, isLoading: monthlyLoading } = useQuery({
        queryKey: ['worker', id, 'monthly'],
        queryFn: () => getWorkerMonthlySummary(id),
        enabled: !!id && activeTab === 'monthly',
    });

    const { data: yearlyData, isLoading: yearlyLoading } = useQuery({
        queryKey: ['worker', id, 'yearly'],
        queryFn: () => getWorkerYearlySummary(id),
        enabled: !!id && activeTab === 'yearly',
    });

    const toggleDay = (date: string) => {
        setExpandedDays((prev) => {
            const next = new Set(prev);
            if (next.has(date)) {
                next.delete(date);
            } else {
                next.add(date);
            }
            return next;
        });
    };

    const tabs: { value: TabType; label: string }[] = [
        { value: 'logs', label: 'Attendance Logs' },
        { value: 'weekly', label: 'This Week' },
        { value: 'monthly', label: 'This Month' },
        { value: 'yearly', label: 'This Year' },
    ];

    const isLoading = logsLoading ||
        (activeTab === 'weekly' && weeklyLoading) ||
        (activeTab === 'monthly' && monthlyLoading) ||
        (activeTab === 'yearly' && yearlyLoading);

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center gap-4">
                <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => navigate('/reports')}
                    className="flex items-center gap-2"
                >
                    <ArrowLeft className="w-4 h-4" />
                    Back
                </Button>
            </div>

            {/* Worker Info */}
            {logsData?.worker && (
                <Card className="bg-gradient-to-r from-blue-50 to-purple-50 border-blue-200">
                    <div className="flex items-center gap-4">
                        <div className="p-4 bg-white rounded-full shadow-sm">
                            <User className="w-8 h-8 text-blue-600" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">
                                {logsData.worker.name}
                            </h1>
                            {logsData.worker.employee_id && (
                                <p className="text-gray-600">ID: {logsData.worker.employee_id}</p>
                            )}
                        </div>
                    </div>
                    {logsData.summary && (
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6 pt-4 border-t border-blue-200">
                            <div>
                                <p className="text-sm text-gray-500">Total Time (30 days)</p>
                                <p className="text-xl font-semibold text-gray-900">
                                    {formatMinutes(logsData.summary.total_minutes)}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm text-gray-500">Days Worked</p>
                                <p className="text-xl font-semibold text-gray-900">
                                    {logsData.summary.days_worked}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm text-gray-500">Days Absent</p>
                                <p className="text-xl font-semibold text-gray-900">
                                    {logsData.summary.days_absent}
                                </p>
                            </div>
                            <div>
                                <p className="text-sm text-gray-500">Total Logs</p>
                                <p className="text-xl font-semibold text-gray-900">
                                    {logsData.total_logs}
                                </p>
                            </div>
                        </div>
                    )}
                </Card>
            )}

            {/* Tabs */}
            <div className="flex gap-1 bg-gray-100 rounded-lg p-1 w-fit">
                {tabs.map((tab) => (
                    <button
                        key={tab.value}
                        onClick={() => setActiveTab(tab.value)}
                        className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                            activeTab === tab.value
                                ? 'bg-white text-gray-900 shadow-sm'
                                : 'text-gray-600 hover:text-gray-900'
                        }`}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>

            {/* Content */}
            {isLoading ? (
                <div className="flex items-center justify-center h-64">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" />
                </div>
            ) : (
                <>
                    {/* Logs Tab */}
                    {activeTab === 'logs' && logsData && (
                        <div className="space-y-3">
                            {logsData.days.length === 0 ? (
                                <Card className="text-center py-12">
                                    <Calendar className="w-12 h-12 mx-auto mb-4 text-gray-300" />
                                    <p className="text-gray-500">No attendance logs in the last 30 days</p>
                                </Card>
                            ) : (
                                logsData.days.map((day) => (
                                    <DayCard
                                        key={day.date}
                                        day={day}
                                        isExpanded={expandedDays.has(day.date)}
                                        onToggle={() => toggleDay(day.date)}
                                    />
                                ))
                            )}
                        </div>
                    )}

                    {/* Weekly Tab */}
                    {activeTab === 'weekly' && weeklyData && (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <SummaryCard
                                title="Weekly Summary"
                                icon={Calendar}
                                iconColor="bg-blue-50 text-blue-600"
                            >
                                <p className="text-sm text-gray-500 mb-4">
                                    {formatDate(weeklyData.week_start)} - {formatDate(weeklyData.week_end)}
                                </p>
                                <div className="space-y-1">
                                    <StatItem label="Total Time" value={formatMinutes(weeklyData.summary.total_minutes)} />
                                    <StatItem label="Regular Time" value={formatMinutes(weeklyData.summary.regular_minutes)} />
                                    <StatItem label="Overtime" value={formatMinutes(weeklyData.summary.overtime_minutes)} />
                                    <StatItem label="Days Worked" value={weeklyData.summary.days_worked} />
                                </div>
                            </SummaryCard>
                            <SummaryCard
                                title="Punctuality"
                                icon={Clock}
                                iconColor="bg-purple-50 text-purple-600"
                            >
                                <div className="space-y-1">
                                    <StatItem
                                        label="Late Arrivals"
                                        value={weeklyData.summary.late_count}
                                    />
                                    <StatItem
                                        label="Early Departures"
                                        value={weeklyData.summary.early_departure_count}
                                    />
                                    {weeklyData.summary.average_checkin_time && (
                                        <StatItem
                                            label="Avg. Check-in"
                                            value={formatTime(weeklyData.summary.average_checkin_time)}
                                        />
                                    )}
                                    {weeklyData.summary.average_checkout_time && (
                                        <StatItem
                                            label="Avg. Check-out"
                                            value={formatTime(weeklyData.summary.average_checkout_time)}
                                        />
                                    )}
                                </div>
                            </SummaryCard>
                        </div>
                    )}

                    {/* Monthly Tab */}
                    {activeTab === 'monthly' && monthlyData && (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <SummaryCard
                                title="Monthly Summary"
                                icon={Calendar}
                                iconColor="bg-green-50 text-green-600"
                            >
                                <p className="text-sm text-gray-500 mb-4">
                                    {monthlyData.month}
                                </p>
                                <div className="space-y-1">
                                    <StatItem label="Total Time" value={formatMinutes(monthlyData.summary.total_minutes)} />
                                    <StatItem label="Regular Time" value={formatMinutes(monthlyData.summary.regular_minutes)} />
                                    <StatItem label="Overtime" value={formatMinutes(monthlyData.summary.overtime_minutes)} />
                                    <StatItem label="Days Worked" value={monthlyData.summary.days_worked} />
                                </div>
                            </SummaryCard>
                            <SummaryCard
                                title="Performance"
                                icon={TrendingUp}
                                iconColor="bg-yellow-50 text-yellow-600"
                            >
                                <div className="space-y-1">
                                    <StatItem
                                        label="Late Arrivals"
                                        value={monthlyData.summary.late_count}
                                    />
                                    <StatItem
                                        label="Early Departures"
                                        value={monthlyData.summary.early_departure_count}
                                    />
                                    {monthlyData.summary.average_checkin_time && (
                                        <StatItem
                                            label="Avg. Check-in"
                                            value={formatTime(monthlyData.summary.average_checkin_time)}
                                        />
                                    )}
                                    {monthlyData.summary.average_checkout_time && (
                                        <StatItem
                                            label="Avg. Check-out"
                                            value={formatTime(monthlyData.summary.average_checkout_time)}
                                        />
                                    )}
                                </div>
                            </SummaryCard>
                        </div>
                    )}

                    {/* Yearly Tab */}
                    {activeTab === 'yearly' && yearlyData && (
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <SummaryCard
                                title="Yearly Summary"
                                icon={Calendar}
                                iconColor="bg-indigo-50 text-indigo-600"
                            >
                                <p className="text-sm text-gray-500 mb-4">
                                    {yearlyData.year}
                                </p>
                                <div className="space-y-1">
                                    <StatItem label="Total Time" value={formatMinutes(yearlyData.summary.total_minutes)} />
                                    <StatItem label="Regular Time" value={formatMinutes(yearlyData.summary.regular_minutes)} />
                                    <StatItem label="Overtime" value={formatMinutes(yearlyData.summary.overtime_minutes)} />
                                    <StatItem label="Days Worked" value={yearlyData.summary.days_worked} />
                                </div>
                            </SummaryCard>
                            <SummaryCard
                                title="Annual Performance"
                                icon={AlertTriangle}
                                iconColor="bg-orange-50 text-orange-600"
                            >
                                <div className="space-y-1">
                                    <StatItem
                                        label="Late Arrivals"
                                        value={yearlyData.summary.late_count}
                                    />
                                    <StatItem
                                        label="Early Departures"
                                        value={yearlyData.summary.early_departure_count}
                                    />
                                    {yearlyData.summary.average_checkin_time && (
                                        <StatItem
                                            label="Avg. Check-in"
                                            value={formatTime(yearlyData.summary.average_checkin_time)}
                                        />
                                    )}
                                    {yearlyData.summary.average_checkout_time && (
                                        <StatItem
                                            label="Avg. Check-out"
                                            value={formatTime(yearlyData.summary.average_checkout_time)}
                                        />
                                    )}
                                </div>
                            </SummaryCard>
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
