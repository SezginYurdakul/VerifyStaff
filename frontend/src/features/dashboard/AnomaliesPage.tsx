import { useSearchParams, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import Card from '@/components/ui/Card';
import Button from '@/components/ui/Button';
import { getDashboardAnomalies, type AnomalyItem, type InactiveWorker } from '@/api/dashboard';
import {
    ArrowLeft,
    Flag,
    LogOut,
    Clock,
    UserX,
    AlertCircle,
    User,
    Calendar,
    FileText,
} from 'lucide-react';

type AnomalyType = 'flagged' | 'missing_checkouts' | 'late_arrivals' | 'inactive_workers';

function formatDateTime(timeString: string | null | undefined): string {
    if (!timeString) return '-';
    try {
        const date = new Date(timeString);
        return date.toLocaleString('tr-TR', {
            weekday: 'short',
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return timeString;
    }
}

function formatDate(timeString: string | null | undefined): string {
    if (!timeString) return '-';
    try {
        const date = new Date(timeString);
        return date.toLocaleDateString('tr-TR', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    } catch {
        return timeString;
    }
}

function formatTime(timeString: string | null | undefined): string {
    if (!timeString) return '-';
    try {
        const date = new Date(timeString);
        return date.toLocaleTimeString('tr-TR', {
            hour: '2-digit',
            minute: '2-digit',
        });
    } catch {
        return timeString;
    }
}

const typeConfig = {
    flagged: {
        title: 'Flagged Records',
        description: 'Records that have been flagged for review due to suspicious activity or policy violations',
        icon: Flag,
        iconBg: 'bg-red-100',
        iconColor: 'text-red-600',
        cardBg: 'bg-red-50 border-red-200',
        badgeColor: 'bg-red-100 text-red-800',
    },
    missing_checkouts: {
        title: 'Missing Checkouts',
        description: 'Workers who checked in but did not check out. These may need manual correction.',
        icon: LogOut,
        iconBg: 'bg-yellow-100',
        iconColor: 'text-yellow-600',
        cardBg: 'bg-yellow-50 border-yellow-200',
        badgeColor: 'bg-yellow-100 text-yellow-800',
    },
    late_arrivals: {
        title: 'Late Arrivals This Week',
        description: 'Workers who arrived after the scheduled start time this week',
        icon: Clock,
        iconBg: 'bg-blue-100',
        iconColor: 'text-blue-600',
        cardBg: 'bg-blue-50 border-blue-200',
        badgeColor: 'bg-blue-100 text-blue-800',
    },
    inactive_workers: {
        title: 'Inactive Workers (Last 7 Days)',
        description: 'Active workers who have not recorded any attendance in the last 7 days',
        icon: UserX,
        iconBg: 'bg-gray-100',
        iconColor: 'text-gray-600',
        cardBg: 'bg-gray-50 border-gray-200',
        badgeColor: 'bg-gray-100 text-gray-800',
    },
};

function FlaggedRecordCard({ item }: { item: AnomalyItem }) {
    return (
        <Card className="border border-red-200 hover:shadow-md transition-shadow">
            <div className="flex items-start gap-4">
                <div className="p-3 bg-red-100 rounded-full">
                    <Flag className="w-6 h-6 text-red-600" />
                </div>
                <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between mb-2">
                        <h4 className="font-semibold text-gray-900 text-lg">
                            {item.worker?.name || 'Unknown Worker'}
                        </h4>
                        <span className="px-3 py-1 bg-red-100 text-red-800 text-xs font-medium rounded-full">
                            High Priority
                        </span>
                    </div>

                    <div className="space-y-2 text-sm">
                        <div className="flex items-center gap-2 text-gray-600">
                            <User className="w-4 h-4" />
                            <span>Worker ID: {item.worker?.id || '-'}</span>
                        </div>
                        <div className="flex items-center gap-2 text-gray-600">
                            <Calendar className="w-4 h-4" />
                            <span>{formatDateTime(item.time)}</span>
                        </div>
                        <div className="flex items-start gap-2 text-gray-600">
                            <FileText className="w-4 h-4 mt-0.5" />
                            <span className="flex-1">
                                <strong>Reason:</strong> {item.reason || 'No reason specified'}
                            </span>
                        </div>
                    </div>

                    <div className="mt-3 pt-3 border-t border-red-100 text-xs text-gray-500">
                        Log ID: #{item.log_id}
                    </div>
                </div>
            </div>
        </Card>
    );
}

function MissingCheckoutCard({ item }: { item: AnomalyItem }) {
    return (
        <Card className="border border-yellow-200 hover:shadow-md transition-shadow">
            <div className="flex items-start gap-4">
                <div className="p-3 bg-yellow-100 rounded-full">
                    <LogOut className="w-6 h-6 text-yellow-600" />
                </div>
                <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between mb-2">
                        <h4 className="font-semibold text-gray-900 text-lg">
                            {item.worker?.name || 'Unknown Worker'}
                        </h4>
                        <span className="px-3 py-1 bg-yellow-100 text-yellow-800 text-xs font-medium rounded-full">
                            Needs Review
                        </span>
                    </div>

                    <div className="space-y-2 text-sm">
                        <div className="flex items-center gap-2 text-gray-600">
                            <User className="w-4 h-4" />
                            <span>Worker ID: {item.worker?.id || '-'}</span>
                        </div>
                        <div className="flex items-center gap-2 text-gray-600">
                            <Calendar className="w-4 h-4" />
                            <span>Check-in Date: {formatDate(item.checkin_time)}</span>
                        </div>
                        <div className="flex items-center gap-2 text-green-600">
                            <Clock className="w-4 h-4" />
                            <span>Check-in Time: {formatTime(item.checkin_time)}</span>
                        </div>
                        <div className="flex items-center gap-2 text-red-600">
                            <LogOut className="w-4 h-4" />
                            <span>Check-out: Not recorded</span>
                        </div>
                    </div>

                    <div className="mt-3 pt-3 border-t border-yellow-100 text-xs text-gray-500">
                        Log ID: #{item.log_id}
                    </div>
                </div>
            </div>
        </Card>
    );
}

function LateArrivalCard({ item }: { item: AnomalyItem }) {
    return (
        <Card className="border border-blue-200 hover:shadow-md transition-shadow">
            <div className="flex items-start gap-4">
                <div className="p-3 bg-blue-100 rounded-full">
                    <Clock className="w-6 h-6 text-blue-600" />
                </div>
                <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between mb-2">
                        <h4 className="font-semibold text-gray-900 text-lg">
                            {item.worker?.name || 'Unknown Worker'}
                        </h4>
                        <span className="px-3 py-1 bg-blue-100 text-blue-800 text-xs font-medium rounded-full">
                            Late
                        </span>
                    </div>

                    <div className="space-y-2 text-sm">
                        <div className="flex items-center gap-2 text-gray-600">
                            <User className="w-4 h-4" />
                            <span>Worker ID: {item.worker?.id || '-'}</span>
                        </div>
                        <div className="flex items-center gap-2 text-gray-600">
                            <Calendar className="w-4 h-4" />
                            <span>{formatDate(item.time)}</span>
                        </div>
                        <div className="flex items-center gap-2 text-blue-600">
                            <Clock className="w-4 h-4" />
                            <span>Arrived at: {formatTime(item.time)}</span>
                        </div>
                    </div>

                    <div className="mt-3 pt-3 border-t border-blue-100 text-xs text-gray-500">
                        Log ID: #{item.log_id}
                    </div>
                </div>
            </div>
        </Card>
    );
}

function InactiveWorkerCard({ item }: { item: InactiveWorker }) {
    return (
        <Card className="border border-gray-200 hover:shadow-md transition-shadow">
            <div className="flex items-start gap-4">
                <div className="p-3 bg-gray-100 rounded-full">
                    <UserX className="w-6 h-6 text-gray-600" />
                </div>
                <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between mb-2">
                        <h4 className="font-semibold text-gray-900 text-lg">
                            {item.name}
                        </h4>
                        <span className="px-3 py-1 bg-gray-100 text-gray-800 text-xs font-medium rounded-full">
                            No Activity
                        </span>
                    </div>

                    <div className="space-y-2 text-sm">
                        <div className="flex items-center gap-2 text-gray-600">
                            <User className="w-4 h-4" />
                            <span>Worker ID: {item.id}</span>
                        </div>
                        {item.employee_id && (
                            <div className="flex items-center gap-2 text-gray-600">
                                <FileText className="w-4 h-4" />
                                <span>Employee ID: {item.employee_id}</span>
                            </div>
                        )}
                        <div className="flex items-center gap-2 text-orange-600">
                            <AlertCircle className="w-4 h-4" />
                            <span>No attendance records in last 7 days</span>
                        </div>
                    </div>
                </div>
            </div>
        </Card>
    );
}

export default function AnomaliesPage() {
    const [searchParams] = useSearchParams();
    const navigate = useNavigate();
    const type = (searchParams.get('type') || 'flagged') as AnomalyType;

    const { data: anomalies, isLoading } = useQuery({
        queryKey: ['dashboard', 'anomalies', 100],
        queryFn: () => getDashboardAnomalies(100),
    });

    const config = typeConfig[type] || typeConfig.flagged;
    const Icon = config.icon;

    const getItems = () => {
        if (!anomalies) return [];
        switch (type) {
            case 'flagged':
                return anomalies.flagged;
            case 'missing_checkouts':
                return anomalies.missing_checkouts;
            case 'late_arrivals':
                return anomalies.late_arrivals;
            case 'inactive_workers':
                return anomalies.inactive_workers;
            default:
                return [];
        }
    };

    const getCount = () => {
        if (!anomalies) return 0;
        switch (type) {
            case 'flagged':
                return anomalies.summary.flagged_count;
            case 'missing_checkouts':
                return anomalies.summary.missing_checkouts_count;
            case 'late_arrivals':
                return anomalies.summary.late_arrivals_this_week;
            case 'inactive_workers':
                return anomalies.summary.inactive_workers;
            default:
                return 0;
        }
    };

    const items = getItems();
    const totalCount = getCount();

    const renderCard = (item: AnomalyItem | InactiveWorker, index: number) => {
        switch (type) {
            case 'flagged':
                return <FlaggedRecordCard key={(item as AnomalyItem).log_id || index} item={item as AnomalyItem} />;
            case 'missing_checkouts':
                return <MissingCheckoutCard key={(item as AnomalyItem).log_id || index} item={item as AnomalyItem} />;
            case 'late_arrivals':
                return <LateArrivalCard key={(item as AnomalyItem).log_id || index} item={item as AnomalyItem} />;
            case 'inactive_workers':
                return <InactiveWorkerCard key={(item as InactiveWorker).id || index} item={item as InactiveWorker} />;
            default:
                return null;
        }
    };

    if (isLoading) {
        return (
            <div className="flex items-center justify-center h-64">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center gap-4">
                <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => navigate('/')}
                    className="flex items-center gap-2"
                >
                    <ArrowLeft className="w-4 h-4" />
                    Back
                </Button>
            </div>

            {/* Title Card */}
            <Card className={`border ${config.cardBg}`}>
                <div className="flex items-start gap-4">
                    <div className={`p-4 ${config.iconBg} rounded-xl`}>
                        <Icon className={`w-8 h-8 ${config.iconColor}`} />
                    </div>
                    <div className="flex-1">
                        <div className="flex items-center justify-between">
                            <h1 className="text-2xl font-bold text-gray-900">{config.title}</h1>
                            <span className={`px-4 py-2 ${config.badgeColor} text-lg font-bold rounded-full`}>
                                {totalCount}
                            </span>
                        </div>
                        <p className="text-gray-600 mt-1">{config.description}</p>
                    </div>
                </div>
            </Card>

            {/* Type Tabs */}
            <div className="flex gap-2 flex-wrap">
                {(Object.keys(typeConfig) as AnomalyType[]).map((t) => {
                    const tc = typeConfig[t];
                    const count = anomalies?.summary ? {
                        flagged: anomalies.summary.flagged_count,
                        missing_checkouts: anomalies.summary.missing_checkouts_count,
                        late_arrivals: anomalies.summary.late_arrivals_this_week,
                        inactive_workers: anomalies.summary.inactive_workers,
                    }[t] : 0;

                    return (
                        <button
                            key={t}
                            onClick={() => navigate(`/anomalies?type=${t}`)}
                            className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2 ${
                                type === t
                                    ? `${tc.iconBg} ${tc.iconColor}`
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                            }`}
                        >
                            <tc.icon className="w-4 h-4" />
                            {tc.title}
                            {count > 0 && (
                                <span className={`ml-1 px-2 py-0.5 rounded-full text-xs ${
                                    type === t ? 'bg-white/50' : 'bg-gray-200'
                                }`}>
                                    {count}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>

            {/* Items List */}
            {items.length === 0 ? (
                <Card className="text-center py-12">
                    <AlertCircle className="w-16 h-16 mx-auto mb-4 text-gray-300" />
                    <h3 className="text-lg font-medium text-gray-900">No items found</h3>
                    <p className="text-gray-500 mt-1">There are no {config.title.toLowerCase()} to display.</p>
                </Card>
            ) : (
                <div className="grid gap-4">
                    {items.map((item, index) => renderCard(item, index))}
                </div>
            )}
        </div>
    );
}
