import { useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import {
    AreaChart,
    Area,
    BarChart,
    Bar,
    XAxis,
    YAxis,
    CartesianGrid,
    Tooltip,
    ResponsiveContainer,
    Legend,
} from 'recharts';
import Card from '@/components/ui/Card';
import {
    getDashboardOverview,
    getDashboardTrends,
    getDashboardAnomalies,
} from '@/api/dashboard';
import {
    Users,
    Clock,
    AlertTriangle,
    TrendingUp,
    UserCheck,
    UserX,
    Timer,
    CalendarDays,
} from 'lucide-react';

function KPICard({
    title,
    value,
    subtitle,
    icon: Icon,
    trend,
    color = 'blue',
}: {
    title: string;
    value: string | number;
    subtitle?: string;
    icon: React.ElementType;
    trend?: { value: number; label: string };
    color?: 'blue' | 'green' | 'yellow' | 'red' | 'purple';
}) {
    const colors = {
        blue: 'bg-blue-50 text-blue-600',
        green: 'bg-green-50 text-green-600',
        yellow: 'bg-yellow-50 text-yellow-600',
        red: 'bg-red-50 text-red-600',
        purple: 'bg-purple-50 text-purple-600',
    };

    return (
        <Card className="relative overflow-hidden">
            <div className="flex items-start justify-between">
                <div>
                    <p className="text-sm font-medium text-gray-500">{title}</p>
                    <p className="mt-1 text-3xl font-semibold text-gray-900">{value}</p>
                    {subtitle && (
                        <p className="mt-1 text-sm text-gray-500">{subtitle}</p>
                    )}
                    {trend && (
                        <p className={`mt-2 text-sm ${trend.value >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                            {trend.value >= 0 ? '↑' : '↓'} {Math.abs(trend.value)}% {trend.label}
                        </p>
                    )}
                </div>
                <div className={`p-3 rounded-lg ${colors[color]}`}>
                    <Icon className="w-6 h-6" />
                </div>
            </div>
        </Card>
    );
}

function AlertCard({
    title,
    count,
    severity,
    items,
    onClick,
}: {
    title: string;
    count: number;
    severity: 'high' | 'medium' | 'low' | 'gray';
    items: Array<{ worker?: { name: string } | null; reason?: string; time?: string; name?: string }>;
    onClick?: () => void;
}) {
    const severityColors = {
        high: 'border-l-red-500 bg-red-50 hover:bg-red-100',
        medium: 'border-l-yellow-500 bg-yellow-50 hover:bg-yellow-100',
        low: 'border-l-blue-500 bg-blue-50 hover:bg-blue-100',
        gray: 'border-l-gray-400 bg-gray-50 hover:bg-gray-100',
    };

    if (count === 0) return null;

    return (
        <div
            className={`border-l-4 rounded-r-lg p-4 cursor-pointer transition-colors ${severityColors[severity]}`}
            onClick={onClick}
        >
            <div className="flex items-center justify-between mb-2">
                <h4 className="font-medium text-gray-900">{title}</h4>
                <span className="text-sm font-semibold text-gray-700">{count}</span>
            </div>
            <ul className="space-y-1">
                {items.slice(0, 3).map((item, idx) => (
                    <li key={idx} className="text-sm text-gray-600">
                        {item.worker?.name || item.name || 'Unknown'} {item.reason && `- ${item.reason}`}
                    </li>
                ))}
                {count > 3 && (
                    <li className="text-sm text-gray-500 font-medium">Click to see all {count}...</li>
                )}
            </ul>
        </div>
    );
}

export default function DashboardPage() {
    const navigate = useNavigate();

    const { data: overview, isLoading: overviewLoading } = useQuery({
        queryKey: ['dashboard', 'overview'],
        queryFn: getDashboardOverview,
        refetchInterval: 60000, // Refresh every minute
    });

    const { data: trends, isLoading: trendsLoading } = useQuery({
        queryKey: ['dashboard', 'trends', 7],
        queryFn: () => getDashboardTrends(7),
    });

    const { data: anomalies, isLoading: anomaliesLoading } = useQuery({
        queryKey: ['dashboard', 'anomalies', 5],
        queryFn: () => getDashboardAnomalies(5), // Just preview items
    });

    if (overviewLoading) {
        return (
            <div className="flex items-center justify-center h-64">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Header */}
            <div>
                <h1 className="text-2xl font-bold text-gray-900">Dashboard</h1>
                <p className="text-gray-500">
                    Overview for {overview?.date || 'today'}
                </p>
            </div>

            {/* KPI Cards */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <KPICard
                    title="Active Workers"
                    value={overview?.active_workers || 0}
                    subtitle="Total registered"
                    icon={Users}
                    color="blue"
                />
                <KPICard
                    title="Today's Attendance"
                    value={`${overview?.today.attendance_rate || 0}%`}
                    subtitle={`${overview?.today.checkins || 0} check-ins`}
                    icon={UserCheck}
                    color="green"
                />
                <KPICard
                    title="Currently Working"
                    value={overview?.today.currently_working || 0}
                    subtitle="On site now"
                    icon={Clock}
                    color="purple"
                />
                <KPICard
                    title="Alerts"
                    value={
                        (overview?.alerts.flagged_records || 0) +
                        (overview?.alerts.missing_checkouts_today || 0)
                    }
                    subtitle="Requires attention"
                    icon={AlertTriangle}
                    color={
                        (overview?.alerts.flagged_records || 0) > 0 ? 'red' : 'yellow'
                    }
                />
            </div>

            {/* Week & Month Stats */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <Card>
                    <h3 className="text-lg font-semibold text-gray-900 mb-4">This Week</h3>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="flex items-center gap-3">
                            <div className="p-2 bg-blue-50 rounded-lg">
                                <Timer className="w-5 h-5 text-blue-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-semibold">{overview?.this_week.total_hours || 0}h</p>
                                <p className="text-sm text-gray-500">Total Hours</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
                            <div className="p-2 bg-purple-50 rounded-lg">
                                <TrendingUp className="w-5 h-5 text-purple-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-semibold">{overview?.this_week.overtime_hours || 0}h</p>
                                <p className="text-sm text-gray-500">Overtime</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
                            <div className="p-2 bg-green-50 rounded-lg">
                                <Users className="w-5 h-5 text-green-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-semibold">{overview?.this_week.unique_workers || 0}</p>
                                <p className="text-sm text-gray-500">Workers Active</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
                            <div className="p-2 bg-yellow-50 rounded-lg">
                                <UserX className="w-5 h-5 text-yellow-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-semibold">{overview?.this_week.late_arrivals || 0}</p>
                                <p className="text-sm text-gray-500">Late Arrivals</p>
                            </div>
                        </div>
                    </div>
                </Card>

                <Card>
                    <h3 className="text-lg font-semibold text-gray-900 mb-4">This Month</h3>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="flex items-center gap-3">
                            <div className="p-2 bg-blue-50 rounded-lg">
                                <Timer className="w-5 h-5 text-blue-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-semibold">{overview?.this_month.total_hours || 0}h</p>
                                <p className="text-sm text-gray-500">Total Hours</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
                            <div className="p-2 bg-purple-50 rounded-lg">
                                <TrendingUp className="w-5 h-5 text-purple-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-semibold">{overview?.this_month.overtime_hours || 0}h</p>
                                <p className="text-sm text-gray-500">Overtime</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
                            <div className="p-2 bg-green-50 rounded-lg">
                                <Users className="w-5 h-5 text-green-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-semibold">{overview?.this_month.unique_workers || 0}</p>
                                <p className="text-sm text-gray-500">Workers Active</p>
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
                            <div className="p-2 bg-indigo-50 rounded-lg">
                                <CalendarDays className="w-5 h-5 text-indigo-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-semibold">{overview?.this_month.days_with_activity || 0}</p>
                                <p className="text-sm text-gray-500">Active Days</p>
                            </div>
                        </div>
                    </div>
                </Card>
            </div>

            {/* Charts */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {/* Attendance Trend */}
                <Card>
                    <h3 className="text-lg font-semibold text-gray-900 mb-4">
                        Attendance Trend (Last 7 Days)
                    </h3>
                    {trendsLoading ? (
                        <div className="h-64 flex items-center justify-center">
                            <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600" />
                        </div>
                    ) : (
                        <ResponsiveContainer width="100%" height={280}>
                            <AreaChart data={trends?.data || []}>
                                <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                                <XAxis dataKey="day" tick={{ fontSize: 12 }} />
                                <YAxis tick={{ fontSize: 12 }} />
                                <Tooltip />
                                <Legend />
                                <Area
                                    type="monotone"
                                    dataKey="checkins"
                                    name="Check-ins"
                                    stroke="#3b82f6"
                                    fill="#93c5fd"
                                    fillOpacity={0.6}
                                />
                                <Area
                                    type="monotone"
                                    dataKey="checkouts"
                                    name="Check-outs"
                                    stroke="#10b981"
                                    fill="#6ee7b7"
                                    fillOpacity={0.6}
                                />
                            </AreaChart>
                        </ResponsiveContainer>
                    )}
                </Card>

                {/* Hours & Issues */}
                <Card>
                    <h3 className="text-lg font-semibold text-gray-900 mb-4">
                        Hours & Issues (Last 7 Days)
                    </h3>
                    {trendsLoading ? (
                        <div className="h-64 flex items-center justify-center">
                            <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600" />
                        </div>
                    ) : (
                        <ResponsiveContainer width="100%" height={280}>
                            <BarChart data={trends?.data || []}>
                                <CartesianGrid strokeDasharray="3 3" stroke="#f0f0f0" />
                                <XAxis dataKey="day" tick={{ fontSize: 12 }} />
                                <YAxis tick={{ fontSize: 12 }} />
                                <Tooltip />
                                <Legend />
                                <Bar
                                    dataKey="total_hours"
                                    name="Total Hours"
                                    fill="#8b5cf6"
                                    radius={[4, 4, 0, 0]}
                                />
                                <Bar
                                    dataKey="late_arrivals"
                                    name="Late Arrivals"
                                    fill="#f59e0b"
                                    radius={[4, 4, 0, 0]}
                                />
                            </BarChart>
                        </ResponsiveContainer>
                    )}
                </Card>
            </div>

            {/* Anomalies */}
            <Card>
                <h3 className="text-lg font-semibold text-gray-900 mb-4">
                    Alerts & Anomalies
                </h3>
                {anomaliesLoading ? (
                    <div className="h-32 flex items-center justify-center">
                        <div className="animate-spin rounded-full h-6 w-6 border-b-2 border-blue-600" />
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <AlertCard
                            title="Flagged Records"
                            count={anomalies?.summary.flagged_count || 0}
                            severity="high"
                            items={anomalies?.flagged || []}
                            onClick={() => navigate('/anomalies?type=flagged')}
                        />
                        <AlertCard
                            title="Missing Checkouts"
                            count={anomalies?.summary.missing_checkouts_count || 0}
                            severity="medium"
                            items={anomalies?.missing_checkouts || []}
                            onClick={() => navigate('/anomalies?type=missing_checkouts')}
                        />
                        <AlertCard
                            title="Late Arrivals (Week)"
                            count={anomalies?.summary.late_arrivals_this_week || 0}
                            severity="low"
                            items={anomalies?.late_arrivals || []}
                            onClick={() => navigate('/anomalies?type=late_arrivals')}
                        />
                        {anomalies && anomalies.summary.inactive_workers > 0 && (
                            <AlertCard
                                title="Inactive Workers"
                                count={anomalies.summary.inactive_workers}
                                severity="gray"
                                items={anomalies.inactive_workers}
                                onClick={() => navigate('/anomalies?type=inactive_workers')}
                            />
                        )}
                    </div>
                )}
            </Card>
        </div>
    );
}
