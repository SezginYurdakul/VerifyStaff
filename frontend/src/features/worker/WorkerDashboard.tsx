import { useQuery } from '@tanstack/react-query';
import { useAuthStore } from '@/stores/authStore';
import { getAttendanceStatus } from '@/api/attendance';
import {
  getWorkerDailySummary,
  getWorkerWeeklySummary,
  getWorkerMonthlySummary,
} from '@/api/reports';
import Card from '@/components/ui/Card';
import {
  Clock,
  LogIn,
  LogOut,
  Calendar,
  Timer,
  AlertTriangle,
  CheckCircle,
  XCircle,
} from 'lucide-react';

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
    return date.toLocaleTimeString('tr-TR', {
      hour: '2-digit',
      minute: '2-digit',
    });
  } catch {
    return timeString;
  }
}

function StatusBadge({ status }: { status: 'complete' | 'in_progress' | 'absent' }) {
  const styles = {
    complete: 'bg-green-100 text-green-800',
    in_progress: 'bg-blue-100 text-blue-800',
    absent: 'bg-gray-100 text-gray-800',
  };
  const labels = {
    complete: 'Completed',
    in_progress: 'Working',
    absent: 'Not Checked In',
  };
  return (
    <span className={`px-2 py-1 rounded-full text-xs font-medium ${styles[status]}`}>
      {labels[status]}
    </span>
  );
}

export default function WorkerDashboard() {
  const user = useAuthStore((state) => state.user);
  const workerId = user?.id;

  const { data: status, isLoading: statusLoading } = useQuery({
    queryKey: ['attendance', 'status'],
    queryFn: getAttendanceStatus,
    refetchInterval: 30000, // Refresh every 30 seconds
  });

  const { data: dailySummary, isLoading: dailyLoading } = useQuery({
    queryKey: ['reports', 'daily', workerId],
    queryFn: () => getWorkerDailySummary(workerId!),
    enabled: !!workerId,
  });

  const { data: weeklySummary, isLoading: weeklyLoading } = useQuery({
    queryKey: ['reports', 'weekly', workerId],
    queryFn: () => getWorkerWeeklySummary(workerId!),
    enabled: !!workerId,
  });

  const { data: monthlySummary, isLoading: monthlyLoading } = useQuery({
    queryKey: ['reports', 'monthly', workerId],
    queryFn: () => getWorkerMonthlySummary(workerId!),
    enabled: !!workerId,
  });

  const isLoading = statusLoading || dailyLoading;

  if (isLoading) {
    return (
      <div className="flex items-center justify-center h-64">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" />
      </div>
    );
  }

  const todayStatus = dailySummary?.summary.status || 'absent';
  const isCheckedIn = status?.checked_in || false;

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-gray-900">My Dashboard</h1>
        <p className="text-gray-500">
          Welcome back, {user?.name}
        </p>
      </div>

      {/* Current Status Card */}
      <Card className="border-l-4 border-l-blue-500">
        <div className="flex items-center justify-between">
          <div>
            <h3 className="text-lg font-semibold text-gray-900">Current Status</h3>
            <p className="text-sm text-gray-500 mt-1">
              {new Date().toLocaleDateString('tr-TR', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
              })}
            </p>
          </div>
          <div className="flex items-center gap-3">
            <StatusBadge status={todayStatus} />
            <div
              className={`p-3 rounded-full ${
                isCheckedIn ? 'bg-green-100' : 'bg-gray-100'
              }`}
            >
              {isCheckedIn ? (
                <CheckCircle className="w-6 h-6 text-green-600" />
              ) : (
                <XCircle className="w-6 h-6 text-gray-400" />
              )}
            </div>
          </div>
        </div>

        {/* Today's Times */}
        <div className="mt-4 grid grid-cols-2 gap-4">
          <div className="flex items-center gap-3">
            <div className="p-2 bg-green-50 rounded-lg">
              <LogIn className="w-5 h-5 text-green-600" />
            </div>
            <div>
              <p className="text-sm text-gray-500">Check-in</p>
              <p className="text-lg font-semibold">
                {formatTime(dailySummary?.summary.checkin_time || null)}
              </p>
            </div>
          </div>
          <div className="flex items-center gap-3">
            <div className="p-2 bg-red-50 rounded-lg">
              <LogOut className="w-5 h-5 text-red-600" />
            </div>
            <div>
              <p className="text-sm text-gray-500">Check-out</p>
              <p className="text-lg font-semibold">
                {formatTime(dailySummary?.summary.checkout_time || null)}
              </p>
            </div>
          </div>
        </div>

        {/* Today's Stats */}
        {dailySummary && dailySummary.summary.work_minutes > 0 && (
          <div className="mt-4 pt-4 border-t border-gray-100">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Timer className="w-4 h-4 text-gray-400" />
                <span className="text-sm text-gray-600">Today's Hours</span>
              </div>
              <span className="font-semibold">
                {formatMinutes(dailySummary.summary.work_minutes)}
              </span>
            </div>
            {dailySummary.summary.overtime_minutes > 0 && (
              <div className="flex items-center justify-between mt-2">
                <div className="flex items-center gap-2">
                  <Clock className="w-4 h-4 text-purple-400" />
                  <span className="text-sm text-gray-600">Overtime</span>
                </div>
                <span className="font-semibold text-purple-600">
                  +{formatMinutes(dailySummary.summary.overtime_minutes)}
                </span>
              </div>
            )}
            {dailySummary.summary.is_late && (
              <div className="flex items-center gap-2 mt-2 text-yellow-600">
                <AlertTriangle className="w-4 h-4" />
                <span className="text-sm">Late arrival</span>
              </div>
            )}
          </div>
        )}
      </Card>

      {/* Week & Month Stats */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {/* This Week */}
        <Card>
          <div className="flex items-center gap-2 mb-4">
            <Calendar className="w-5 h-5 text-blue-600" />
            <h3 className="text-lg font-semibold text-gray-900">This Week</h3>
          </div>
          {weeklyLoading ? (
            <div className="animate-pulse space-y-3">
              <div className="h-4 bg-gray-200 rounded w-3/4" />
              <div className="h-4 bg-gray-200 rounded w-1/2" />
            </div>
          ) : weeklySummary ? (
            <div className="space-y-3">
              <div className="flex justify-between items-center">
                <span className="text-gray-600">Total Hours</span>
                <span className="font-semibold text-lg">
                  {formatMinutes(weeklySummary.summary.total_minutes)}
                </span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-gray-600">Days Worked</span>
                <span className="font-semibold">{weeklySummary.summary.days_worked}</span>
              </div>
              {weeklySummary.summary.overtime_minutes > 0 && (
                <div className="flex justify-between items-center">
                  <span className="text-gray-600">Overtime</span>
                  <span className="font-semibold text-purple-600">
                    +{formatMinutes(weeklySummary.summary.overtime_minutes)}
                  </span>
                </div>
              )}
              {weeklySummary.summary.late_count > 0 && (
                <div className="flex justify-between items-center">
                  <span className="text-gray-600">Late Arrivals</span>
                  <span className="font-semibold text-yellow-600">
                    {weeklySummary.summary.late_count}
                  </span>
                </div>
              )}
              <div className="pt-2 border-t border-gray-100 text-xs text-gray-400">
                {weeklySummary.week_start} - {weeklySummary.week_end}
              </div>
            </div>
          ) : (
            <p className="text-gray-500 text-sm">No data available</p>
          )}
        </Card>

        {/* This Month */}
        <Card>
          <div className="flex items-center gap-2 mb-4">
            <Calendar className="w-5 h-5 text-green-600" />
            <h3 className="text-lg font-semibold text-gray-900">This Month</h3>
          </div>
          {monthlyLoading ? (
            <div className="animate-pulse space-y-3">
              <div className="h-4 bg-gray-200 rounded w-3/4" />
              <div className="h-4 bg-gray-200 rounded w-1/2" />
            </div>
          ) : monthlySummary ? (
            <div className="space-y-3">
              <div className="flex justify-between items-center">
                <span className="text-gray-600">Total Hours</span>
                <span className="font-semibold text-lg">
                  {formatMinutes(monthlySummary.summary.total_minutes)}
                </span>
              </div>
              <div className="flex justify-between items-center">
                <span className="text-gray-600">Days Worked</span>
                <span className="font-semibold">{monthlySummary.summary.days_worked}</span>
              </div>
              {monthlySummary.summary.overtime_minutes > 0 && (
                <div className="flex justify-between items-center">
                  <span className="text-gray-600">Overtime</span>
                  <span className="font-semibold text-purple-600">
                    +{formatMinutes(monthlySummary.summary.overtime_minutes)}
                  </span>
                </div>
              )}
              {monthlySummary.summary.late_count > 0 && (
                <div className="flex justify-between items-center">
                  <span className="text-gray-600">Late Arrivals</span>
                  <span className="font-semibold text-yellow-600">
                    {monthlySummary.summary.late_count}
                  </span>
                </div>
              )}
              <div className="pt-2 border-t border-gray-100 text-xs text-gray-400">
                {monthlySummary.month}
              </div>
            </div>
          ) : (
            <p className="text-gray-500 text-sm">No data available</p>
          )}
        </Card>
      </div>

      {/* Recent Activity */}
      {status?.today_logs && status.today_logs.length > 0 && (
        <Card>
          <h3 className="text-lg font-semibold text-gray-900 mb-4">Today's Activity</h3>
          <div className="space-y-3">
            {status.today_logs.map((log, index) => (
              <div
                key={log.id || index}
                className="flex items-center justify-between py-2 border-b border-gray-100 last:border-0"
              >
                <div className="flex items-center gap-3">
                  <div
                    className={`p-2 rounded-full ${
                      log.type === 'in' ? 'bg-green-100' : 'bg-red-100'
                    }`}
                  >
                    {log.type === 'in' ? (
                      <LogIn className="w-4 h-4 text-green-600" />
                    ) : (
                      <LogOut className="w-4 h-4 text-red-600" />
                    )}
                  </div>
                  <div>
                    <p className="font-medium">
                      {log.type === 'in' ? 'Check-in' : 'Check-out'}
                    </p>
                    {log.is_late && (
                      <span className="text-xs text-yellow-600">Late</span>
                    )}
                  </div>
                </div>
                <span className="text-gray-600">
                  {formatTime(log.device_time)}
                </span>
              </div>
            ))}
          </div>
        </Card>
      )}
    </div>
  );
}
