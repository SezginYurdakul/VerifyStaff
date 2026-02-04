import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import {
    getAllWorkersDailySummary,
    getAllWorkersWeeklySummary,
    getAllWorkersMonthlySummary,
    type WorkerDailySummary,
    type WorkerWeeklySummary,
    type WorkerMonthlySummary,
} from '@/api/reports';
import {
    Calendar,
    Clock,
    Users,
    ChevronLeft,
    ChevronRight,
    AlertTriangle,
    Timer,
    TrendingUp,
} from 'lucide-react';

type PeriodType = 'daily' | 'weekly' | 'monthly';

function formatDate(dateString: string): string {
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

function PeriodSelector({
    period,
    onChange,
}: {
    period: PeriodType;
    onChange: (p: PeriodType) => void;
}) {
    const options: { value: PeriodType; label: string }[] = [
        { value: 'daily', label: 'Daily' },
        { value: 'weekly', label: 'Weekly' },
        { value: 'monthly', label: 'Monthly' },
    ];

    return (
        <div className="flex gap-1 bg-gray-100 rounded-lg p-1">
            {options.map((opt) => (
                <button
                    key={opt.value}
                    onClick={() => onChange(opt.value)}
                    className={`px-4 py-2 rounded-md text-sm font-medium transition-colors ${
                        period === opt.value
                            ? 'bg-white text-gray-900 shadow-sm'
                            : 'text-gray-600 hover:text-gray-900'
                    }`}
                >
                    {opt.label}
                </button>
            ))}
        </div>
    );
}

function DateNavigator({
    period,
    currentDate,
    onPrev,
    onNext,
    onToday,
    label,
}: {
    period: PeriodType;
    currentDate: Date;
    onPrev: () => void;
    onNext: () => void;
    onToday: () => void;
    label: string;
}) {
    const isToday = (() => {
        const today = new Date();
        if (period === 'daily') {
            return currentDate.toDateString() === today.toDateString();
        }
        if (period === 'weekly') {
            const startOfWeek = new Date(today);
            startOfWeek.setDate(today.getDate() - today.getDay() + 1);
            const currentStart = new Date(currentDate);
            currentStart.setDate(currentDate.getDate() - currentDate.getDay() + 1);
            return startOfWeek.toDateString() === currentStart.toDateString();
        }
        if (period === 'monthly') {
            return (
                currentDate.getMonth() === today.getMonth() &&
                currentDate.getFullYear() === today.getFullYear()
            );
        }
        return false;
    })();

    return (
        <div className="flex items-center gap-2">
            <button
                onClick={onPrev}
                className="p-2 rounded-lg hover:bg-gray-100 transition-colors"
            >
                <ChevronLeft className="w-5 h-5" />
            </button>
            <div className="min-w-[200px] text-center">
                <span className="font-medium text-gray-900">{label}</span>
            </div>
            <button
                onClick={onNext}
                className="p-2 rounded-lg hover:bg-gray-100 transition-colors"
            >
                <ChevronRight className="w-5 h-5" />
            </button>
            {!isToday && (
                <Button variant="secondary" size="sm" onClick={onToday}>
                    Today
                </Button>
            )}
        </div>
    );
}

function DailyTable({
    workers,
    onWorkerClick,
}: {
    workers: WorkerDailySummary[];
    onWorkerClick: (id: number) => void;
}) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full">
                <thead>
                    <tr className="border-b border-gray-200">
                        <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">Worker</th>
                        <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">Work Time</th>
                        <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">Overtime</th>
                        <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">Flags</th>
                    </tr>
                </thead>
                <tbody>
                    {workers.map((worker) => (
                        <tr
                            key={worker.id}
                            className="border-b border-gray-100 hover:bg-gray-50 cursor-pointer"
                            onClick={() => onWorkerClick(worker.id)}
                        >
                            <td className="py-3 px-4">
                                <div>
                                    <p className="font-medium text-gray-900">{worker.name}</p>
                                    {worker.employee_id && (
                                        <p className="text-sm text-gray-500">{worker.employee_id}</p>
                                    )}
                                </div>
                            </td>
                            <td className="py-3 px-4">
                                <div className="flex items-center gap-2">
                                    <Timer className="w-4 h-4 text-blue-500" />
                                    <span className="font-medium text-gray-900">{worker.formatted_time}</span>
                                </div>
                            </td>
                            <td className="py-3 px-4">
                                {worker.overtime_hours > 0 ? (
                                    <div className="flex items-center gap-1 text-purple-600">
                                        <TrendingUp className="w-4 h-4" />
                                        <span>{worker.overtime_hours}h</span>
                                    </div>
                                ) : (
                                    <span className="text-gray-400">-</span>
                                )}
                            </td>
                            <td className="py-3 px-4">
                                <div className="flex gap-1">
                                    {worker.late_arrivals > 0 && (
                                        <span className="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            <AlertTriangle className="w-3 h-3" />
                                            {worker.late_arrivals} Late
                                        </span>
                                    )}
                                    {worker.early_departures > 0 && (
                                        <span className="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                            {worker.early_departures} Early
                                        </span>
                                    )}
                                    {worker.late_arrivals === 0 && worker.early_departures === 0 && (
                                        <span className="text-gray-400">-</span>
                                    )}
                                </div>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function WeeklyTable({
    workers,
    onWorkerClick,
}: {
    workers: WorkerWeeklySummary[];
    onWorkerClick: (id: number) => void;
}) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full">
                <thead>
                    <tr className="border-b border-gray-200">
                        <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">Worker</th>
                        <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">Total Time</th>
                        <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">Days Worked</th>
                        <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">Late</th>
                        <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">Early Dep.</th>
                    </tr>
                </thead>
                <tbody>
                    {workers.map((worker) => (
                        <tr
                            key={worker.id}
                            className="border-b border-gray-100 hover:bg-gray-50 cursor-pointer"
                            onClick={() => onWorkerClick(worker.id)}
                        >
                            <td className="py-3 px-4">
                                <div>
                                    <p className="font-medium text-gray-900">{worker.name}</p>
                                    {worker.employee_id && (
                                        <p className="text-sm text-gray-500">{worker.employee_id}</p>
                                    )}
                                </div>
                            </td>
                            <td className="py-3 px-4">
                                <div className="flex items-center gap-2">
                                    <Timer className="w-4 h-4 text-blue-500" />
                                    <span className="font-medium text-gray-900">{worker.formatted_time}</span>
                                </div>
                            </td>
                            <td className="py-3 px-4">
                                <span className="text-gray-600">{worker.days_worked} days</span>
                                {worker.days_absent > 0 && (
                                    <span className="text-sm text-gray-400 ml-2">({worker.days_absent} absent)</span>
                                )}
                            </td>
                            <td className="py-3 px-4">
                                {worker.late_arrivals > 0 ? (
                                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        <AlertTriangle className="w-3 h-3" />
                                        {worker.late_arrivals}
                                    </span>
                                ) : (
                                    <span className="text-gray-400">-</span>
                                )}
                            </td>
                            <td className="py-3 px-4">
                                {worker.early_departures > 0 ? (
                                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                        {worker.early_departures}
                                    </span>
                                ) : (
                                    <span className="text-gray-400">-</span>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function MonthlyTable({
    workers,
    onWorkerClick,
}: {
    workers: WorkerMonthlySummary[];
    onWorkerClick: (id: number) => void;
}) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full">
                <thead>
                    <tr className="border-b border-gray-200">
                        <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">Worker</th>
                        <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">Total Time</th>
                        <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">Overtime</th>
                        <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">Days</th>
                        <th className="text-left py-3 px-4 text-sm font-medium text-gray-500">Late</th>
                    </tr>
                </thead>
                <tbody>
                    {workers.map((worker) => (
                        <tr
                            key={worker.id}
                            className="border-b border-gray-100 hover:bg-gray-50 cursor-pointer"
                            onClick={() => onWorkerClick(worker.id)}
                        >
                            <td className="py-3 px-4">
                                <div>
                                    <p className="font-medium text-gray-900">{worker.name}</p>
                                    {worker.employee_id && (
                                        <p className="text-sm text-gray-500">{worker.employee_id}</p>
                                    )}
                                </div>
                            </td>
                            <td className="py-3 px-4">
                                <div className="flex items-center gap-2">
                                    <Timer className="w-4 h-4 text-blue-500" />
                                    <span className="font-medium text-gray-900">{worker.formatted_time}</span>
                                </div>
                            </td>
                            <td className="py-3 px-4">
                                {worker.overtime_hours > 0 ? (
                                    <div className="flex items-center gap-1 text-purple-600">
                                        <TrendingUp className="w-4 h-4" />
                                        <span>{worker.overtime_hours}h</span>
                                    </div>
                                ) : (
                                    <span className="text-gray-400">-</span>
                                )}
                            </td>
                            <td className="py-3 px-4">
                                <span className="text-gray-600">{worker.days_worked}</span>
                                <span className="text-gray-400"> / {worker.days_worked + worker.days_absent}</span>
                            </td>
                            <td className="py-3 px-4">
                                {worker.late_arrivals > 0 ? (
                                    <span className="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        <AlertTriangle className="w-3 h-3" />
                                        {worker.late_arrivals}
                                    </span>
                                ) : (
                                    <span className="text-gray-400">-</span>
                                )}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function ReportsPage() {
    const navigate = useNavigate();
    const [period, setPeriod] = useState<PeriodType>('daily');
    const [currentDate, setCurrentDate] = useState(new Date());

    const getDateString = () => currentDate.toISOString().split('T')[0];
    const getMonthString = () => {
        const year = currentDate.getFullYear();
        const month = String(currentDate.getMonth() + 1).padStart(2, '0');
        return `${year}-${month}`;
    };

    const { data: dailyData, isLoading: dailyLoading } = useQuery({
        queryKey: ['reports', 'daily', getDateString()],
        queryFn: () => getAllWorkersDailySummary(getDateString()),
        enabled: period === 'daily',
    });

    const { data: weeklyData, isLoading: weeklyLoading } = useQuery({
        queryKey: ['reports', 'weekly', getDateString()],
        queryFn: () => getAllWorkersWeeklySummary(getDateString()),
        enabled: period === 'weekly',
    });

    const { data: monthlyData, isLoading: monthlyLoading } = useQuery({
        queryKey: ['reports', 'monthly', getMonthString()],
        queryFn: () => getAllWorkersMonthlySummary(getMonthString()),
        enabled: period === 'monthly',
    });

    const isLoading =
        (period === 'daily' && dailyLoading) ||
        (period === 'weekly' && weeklyLoading) ||
        (period === 'monthly' && monthlyLoading);

    const navigatePeriod = (direction: 'prev' | 'next') => {
        const newDate = new Date(currentDate);
        if (period === 'daily') {
            newDate.setDate(newDate.getDate() + (direction === 'next' ? 1 : -1));
        } else if (period === 'weekly') {
            newDate.setDate(newDate.getDate() + (direction === 'next' ? 7 : -7));
        } else if (period === 'monthly') {
            newDate.setMonth(newDate.getMonth() + (direction === 'next' ? 1 : -1));
        }
        setCurrentDate(newDate);
    };

    const getDateLabel = () => {
        if (period === 'daily') {
            return formatDate(currentDate.toISOString());
        }
        if (period === 'weekly' && weeklyData) {
            return `${formatDate(weeklyData.week_start)} - ${formatDate(weeklyData.week_end)}`;
        }
        if (period === 'monthly') {
            return currentDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        }
        return '';
    };

    const handleWorkerClick = (workerId: number) => {
        navigate(`/reports/worker/${workerId}`);
    };

    const totalWorkers =
        period === 'daily'
            ? dailyData?.total
            : period === 'weekly'
            ? weeklyData?.total
            : monthlyData?.total;

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">Reports</h1>
                    <p className="text-gray-500">
                        View attendance reports for all workers
                    </p>
                </div>
                <PeriodSelector period={period} onChange={setPeriod} />
            </div>

            {/* Summary Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Card className="flex items-center gap-4">
                    <div className="p-3 bg-blue-50 rounded-lg">
                        <Users className="w-6 h-6 text-blue-600" />
                    </div>
                    <div>
                        <p className="text-sm text-gray-500">Total Workers</p>
                        <p className="text-2xl font-semibold text-gray-900">{totalWorkers || 0}</p>
                    </div>
                </Card>
                <Card className="flex items-center gap-4">
                    <div className="p-3 bg-green-50 rounded-lg">
                        <Calendar className="w-6 h-6 text-green-600" />
                    </div>
                    <div>
                        <p className="text-sm text-gray-500">Period</p>
                        <p className="text-lg font-semibold text-gray-900 capitalize">{period}</p>
                    </div>
                </Card>
                <Card className="flex items-center gap-4">
                    <div className="p-3 bg-purple-50 rounded-lg">
                        <Clock className="w-6 h-6 text-purple-600" />
                    </div>
                    <div>
                        <p className="text-sm text-gray-500">Date Range</p>
                        <p className="text-sm font-medium text-gray-900">{getDateLabel()}</p>
                    </div>
                </Card>
            </div>

            {/* Date Navigator */}
            <Card>
                <div className="flex items-center justify-between mb-4">
                    <h3 className="text-lg font-semibold text-gray-900">
                        {period === 'daily' && 'Daily Attendance'}
                        {period === 'weekly' && 'Weekly Summary'}
                        {period === 'monthly' && 'Monthly Summary'}
                    </h3>
                    <DateNavigator
                        period={period}
                        currentDate={currentDate}
                        onPrev={() => navigatePeriod('prev')}
                        onNext={() => navigatePeriod('next')}
                        onToday={() => setCurrentDate(new Date())}
                        label={getDateLabel()}
                    />
                </div>

                {isLoading ? (
                    <div className="flex items-center justify-center h-64">
                        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" />
                    </div>
                ) : (
                    <>
                        {period === 'daily' && dailyData && (
                            <DailyTable workers={dailyData.workers} onWorkerClick={handleWorkerClick} />
                        )}
                        {period === 'weekly' && weeklyData && (
                            <WeeklyTable workers={weeklyData.workers} onWorkerClick={handleWorkerClick} />
                        )}
                        {period === 'monthly' && monthlyData && (
                            <MonthlyTable workers={monthlyData.workers} onWorkerClick={handleWorkerClick} />
                        )}
                        {((period === 'daily' && !dailyData?.workers?.length) ||
                            (period === 'weekly' && !weeklyData?.workers?.length) ||
                            (period === 'monthly' && !monthlyData?.workers?.length)) && (
                            <div className="text-center py-12 text-gray-500">
                                <Users className="w-12 h-12 mx-auto mb-4 text-gray-300" />
                                <p>No data available for this period</p>
                            </div>
                        )}
                    </>
                )}
            </Card>
        </div>
    );
}
