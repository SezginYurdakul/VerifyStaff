import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useAuthStore } from '@/stores/authStore';
import { getAttendanceStatus } from '@/api/attendance';
import {
  getWorkerDailySummary,
  getWorkerWeeklySummary,
  getWorkerMonthlySummary,
  getWorkerLogs,
} from '@/api/reports';
import Card from '@/components/ui/Card';
import Modal from '@/components/ui/Modal';
import {
  Clock,
  LogIn,
  LogOut,
  Calendar,
  Timer,
  AlertTriangle,
  CheckCircle,
  XCircle,
  ChevronLeft,
  ChevronRight,
  Eye,
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
    return date.toLocaleTimeString('en-US', {
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

// Helper to get Monday of a given week
function getMonday(date: Date): Date {
  const d = new Date(date);
  const day = d.getDay();
  const diff = d.getDate() - day + (day === 0 ? -6 : 1);
  return new Date(d.setDate(diff));
}

// Format date as YYYY-MM-DD
function formatDateParam(date: Date): string {
  return date.toISOString().split('T')[0];
}

// Format month as YYYY-MM
function formatMonthParam(date: Date): string {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  return `${year}-${month}`;
}

export default function WorkerDashboard() {
  const user = useAuthStore((state) => state.user);
  const workerId = user?.id;

  // Detail modal state
  const [detailModal, setDetailModal] = useState<{
    isOpen: boolean;
    type: 'week' | 'month';
    from: string;
    to: string;
    title: string;
  } | null>(null);

  // Week navigation state
  const [weekOffset, setWeekOffset] = useState(0);
  const currentWeekMonday = getMonday(new Date());
  const selectedWeekMonday = new Date(currentWeekMonday);
  selectedWeekMonday.setDate(selectedWeekMonday.getDate() + weekOffset * 7);
  const selectedWeekDate = formatDateParam(selectedWeekMonday);
  const isCurrentWeek = weekOffset === 0;

  // Month navigation state
  const [monthOffset, setMonthOffset] = useState(0);
  const currentMonth = new Date();
  currentMonth.setDate(1);
  const selectedMonth = new Date(currentMonth);
  selectedMonth.setMonth(selectedMonth.getMonth() + monthOffset);
  const selectedMonthParam = formatMonthParam(selectedMonth);
  const isCurrentMonth = monthOffset === 0;

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
    queryKey: ['reports', 'weekly', workerId, selectedWeekDate],
    queryFn: () => getWorkerWeeklySummary(workerId!, isCurrentWeek ? undefined : selectedWeekDate),
    enabled: !!workerId,
  });

  const { data: monthlySummary, isLoading: monthlyLoading } = useQuery({
    queryKey: ['reports', 'monthly', workerId, selectedMonthParam],
    queryFn: () => getWorkerMonthlySummary(workerId!, isCurrentMonth ? undefined : selectedMonthParam),
    enabled: !!workerId,
  });

  // Fetch logs for detail modal
  const { data: logsData, isLoading: logsLoading } = useQuery({
    queryKey: ['reports', 'logs', workerId, detailModal?.from, detailModal?.to],
    queryFn: () => getWorkerLogs(workerId!, detailModal!.from, detailModal!.to),
    enabled: !!workerId && !!detailModal?.isOpen,
  });

  const handleOpenWeekDetail = () => {
    if (weeklySummary) {
      setDetailModal({
        isOpen: true,
        type: 'week',
        from: weeklySummary.week_start,
        to: weeklySummary.week_end,
        title: isCurrentWeek ? 'This Week Details' : `Week ${weeklySummary.week_start} - ${weeklySummary.week_end}`,
      });
    }
  };

  const handleOpenMonthDetail = () => {
    if (monthlySummary) {
      setDetailModal({
        isOpen: true,
        type: 'month',
        from: monthlySummary.month_start,
        to: monthlySummary.month_end,
        title: isCurrentMonth ? 'This Month Details' : monthlySummary.month,
      });
    }
  };

  const handleCloseDetail = () => {
    setDetailModal(null);
  };

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
              {new Date().toLocaleDateString('en-US', {
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
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2">
              <Calendar className="w-5 h-5 text-blue-600" />
              <h3 className="text-lg font-semibold text-gray-900">
                {isCurrentWeek ? 'This Week' : 'Week'}
              </h3>
            </div>
            <div className="flex items-center gap-1">
              <button
                onClick={() => setWeekOffset((prev) => prev - 1)}
                className="p-1 hover:bg-gray-100 rounded-full transition-colors"
                title="Previous week"
              >
                <ChevronLeft className="w-5 h-5 text-gray-500" />
              </button>
              {!isCurrentWeek && (
                <button
                  onClick={() => setWeekOffset(0)}
                  className="px-2 py-0.5 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200 transition-colors"
                >
                  Today
                </button>
              )}
              <button
                onClick={() => setWeekOffset((prev) => prev + 1)}
                disabled={isCurrentWeek}
                className={`p-1 rounded-full transition-colors ${
                  isCurrentWeek
                    ? 'text-gray-300 cursor-not-allowed'
                    : 'hover:bg-gray-100 text-gray-500'
                }`}
                title="Next week"
              >
                <ChevronRight className="w-5 h-5" />
              </button>
            </div>
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
              <div className="pt-2 border-t border-gray-100 flex items-center justify-between">
                <span className="text-xs text-gray-400">
                  {weeklySummary.week_start} - {weeklySummary.week_end}
                </span>
                <button
                  onClick={handleOpenWeekDetail}
                  className="flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 transition-colors"
                >
                  <Eye className="w-3.5 h-3.5" />
                  View Details
                </button>
              </div>
            </div>
          ) : (
            <p className="text-gray-500 text-sm">No data available</p>
          )}
        </Card>

        {/* This Month */}
        <Card>
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-2">
              <Calendar className="w-5 h-5 text-green-600" />
              <h3 className="text-lg font-semibold text-gray-900">
                {isCurrentMonth ? 'This Month' : monthlySummary?.month || 'Month'}
              </h3>
            </div>
            <div className="flex items-center gap-1">
              <button
                onClick={() => setMonthOffset((prev) => prev - 1)}
                className="p-1 hover:bg-gray-100 rounded-full transition-colors"
                title="Previous month"
              >
                <ChevronLeft className="w-5 h-5 text-gray-500" />
              </button>
              {!isCurrentMonth && (
                <button
                  onClick={() => setMonthOffset(0)}
                  className="px-2 py-0.5 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200 transition-colors"
                >
                  Today
                </button>
              )}
              <button
                onClick={() => setMonthOffset((prev) => prev + 1)}
                disabled={isCurrentMonth}
                className={`p-1 rounded-full transition-colors ${
                  isCurrentMonth
                    ? 'text-gray-300 cursor-not-allowed'
                    : 'hover:bg-gray-100 text-gray-500'
                }`}
                title="Next month"
              >
                <ChevronRight className="w-5 h-5" />
              </button>
            </div>
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
              <div className="pt-2 border-t border-gray-100 flex items-center justify-between">
                <span className="text-xs text-gray-400">
                  {monthlySummary.month_start} - {monthlySummary.month_end}
                </span>
                <button
                  onClick={handleOpenMonthDetail}
                  className="flex items-center gap-1 text-xs text-green-600 hover:text-green-800 transition-colors"
                >
                  <Eye className="w-3.5 h-3.5" />
                  View Details
                </button>
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

      {/* Detail Modal */}
      <Modal
        isOpen={!!detailModal?.isOpen}
        onClose={handleCloseDetail}
        title={detailModal?.title || 'Details'}
      >
        <div className="space-y-4 max-h-[70vh] overflow-y-auto">
          {logsLoading ? (
            <div className="flex items-center justify-center py-8">
              <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600" />
            </div>
          ) : logsData ? (
            <>
              {/* Summary */}
              <div className="grid grid-cols-2 gap-3 p-3 bg-gray-50 rounded-lg">
                <div className="text-center">
                  <p className="text-2xl font-bold text-gray-900">
                    {formatMinutes(logsData.summary.total_minutes)}
                  </p>
                  <p className="text-xs text-gray-500">Total Hours</p>
                </div>
                <div className="text-center">
                  <p className="text-2xl font-bold text-gray-900">
                    {logsData.summary.days_worked}
                  </p>
                  <p className="text-xs text-gray-500">Days Worked</p>
                </div>
              </div>

              {/* Daily Breakdown */}
              <div className="space-y-3">
                {logsData.days.length === 0 ? (
                  <p className="text-center text-gray-500 py-4">No attendance records</p>
                ) : (
                  logsData.days.map((day) => (
                    <div key={day.date} className="border border-gray-200 rounded-lg overflow-hidden">
                      {/* Day Header */}
                      <div className={`px-3 py-2 flex items-center justify-between ${
                        day.summary.status === 'complete' ? 'bg-green-50' :
                        day.summary.status === 'in_progress' ? 'bg-blue-50' :
                        'bg-gray-50'
                      }`}>
                        <div className="flex items-center gap-2">
                          <span className="font-medium text-gray-900">
                            {new Date(day.date).toLocaleDateString('en-US', {
                              weekday: 'short',
                              month: 'short',
                              day: 'numeric',
                            })}
                          </span>
                          {day.summary.is_late && (
                            <span className="px-1.5 py-0.5 text-xs bg-yellow-100 text-yellow-700 rounded">
                              Late
                            </span>
                          )}
                        </div>
                        <span className="text-sm font-semibold text-gray-700">
                          {day.summary.work_minutes > 0 ? formatMinutes(day.summary.work_minutes) : '-'}
                        </span>
                      </div>

                      {/* Day Logs */}
                      <div className="px-3 py-2 space-y-1.5">
                        {day.logs.map((log) => (
                          <div
                            key={log.id}
                            className="flex items-center justify-between text-sm"
                          >
                            <div className="flex items-center gap-2">
                              {log.type === 'in' ? (
                                <LogIn className="w-3.5 h-3.5 text-green-600" />
                              ) : (
                                <LogOut className="w-3.5 h-3.5 text-red-600" />
                              )}
                              <span className="text-gray-600">
                                {log.type === 'in' ? 'Check-in' : 'Check-out'}
                              </span>
                            </div>
                            <div className="flex items-center gap-2">
                              <span className="text-gray-900 font-medium">
                                {formatTime(log.device_time)}
                              </span>
                              {log.work_minutes && log.work_minutes > 0 && (
                                <span className="text-xs text-gray-400">
                                  ({formatMinutes(log.work_minutes)})
                                </span>
                              )}
                            </div>
                          </div>
                        ))}
                        {day.logs.length === 0 && (
                          <p className="text-xs text-gray-400 text-center py-1">No logs</p>
                        )}
                      </div>
                    </div>
                  ))
                )}
              </div>
            </>
          ) : (
            <p className="text-center text-gray-500 py-4">Failed to load data</p>
          )}
        </div>
      </Modal>
    </div>
  );
}
