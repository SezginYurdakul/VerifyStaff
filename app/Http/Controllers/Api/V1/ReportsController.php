<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceLogResource;
use App\Jobs\CalculateWorkSummary;
use App\Models\AttendanceLog;
use App\Models\User;
use App\Models\WorkSummary;
use App\Services\WorkSummaryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    public function __construct(
        private WorkSummaryService $summaryService
    ) {}

    public function dailySummary(Request $request, int $workerId): JsonResponse
    {
        $user = $request->user();

        if (! $this->canViewWorkerReports($user, $workerId)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $worker = User::where('id', $workerId)->where('role', 'worker')->first();

        if (! $worker) {
            return response()->json(['message' => 'Worker not found'], 404);
        }

        $date = $request->query('date') ? Carbon::parse($request->query('date')) : Carbon::today();

        // Aggregate directly from attendance_logs (no more daily work_summaries)
        $checkoutStats = AttendanceLog::where('worker_id', $workerId)
            ->where('type', 'out')
            ->whereNotNull('work_minutes')
            ->whereDate('device_time', $date)
            ->selectRaw('
                SUM(work_minutes) as total_minutes,
                SUM(CASE WHEN is_overtime = 1 THEN overtime_minutes ELSE 0 END) as overtime_minutes,
                SUM(CASE WHEN is_early_departure = 1 THEN 1 ELSE 0 END) as early_departures
            ')
            ->first();

        $checkinStats = AttendanceLog::where('worker_id', $workerId)
            ->where('type', 'in')
            ->whereDate('device_time', $date)
            ->selectRaw('
                SUM(CASE WHEN is_late = 1 THEN 1 ELSE 0 END) as late_arrivals,
                COUNT(*) as checkin_count
            ')
            ->first();

        $missingCheckouts = AttendanceLog::where('worker_id', $workerId)
            ->where('type', 'in')
            ->whereNull('paired_log_id')
            ->whereDate('device_time', $date)
            ->count();

        $missingCheckins = AttendanceLog::where('worker_id', $workerId)
            ->where('type', 'out')
            ->whereNull('paired_log_id')
            ->whereDate('device_time', $date)
            ->count();

        $totalMinutes = (int) ($checkoutStats->total_minutes ?? 0);
        $overtimeMinutes = (int) ($checkoutStats->overtime_minutes ?? 0);
        $regularMinutes = max(0, $totalMinutes - $overtimeMinutes);

        return response()->json([
            'worker' => [
                'id' => $worker->id,
                'name' => $worker->name,
            ],
            'period' => 'daily',
            'date' => $date->format('Y-m-d'),
            'summary' => [
                'total_hours' => round($totalMinutes / 60, 2),
                'total_minutes' => $totalMinutes,
                'regular_hours' => round($regularMinutes / 60, 2),
                'overtime_hours' => round($overtimeMinutes / 60, 2),
                'formatted_time' => sprintf('%d:%02d', intdiv($totalMinutes, 60), $totalMinutes % 60),
                'late_arrivals' => (int) ($checkinStats->late_arrivals ?? 0),
                'early_departures' => (int) ($checkoutStats->early_departures ?? 0),
                'missing_checkouts' => $missingCheckouts,
                'missing_checkins' => $missingCheckins,
            ],
        ]);
    }

    public function weeklySummary(Request $request, int $workerId): JsonResponse
    {
        $user = $request->user();

        if (!$this->canViewWorkerReports($user, $workerId)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $worker = User::where('id', $workerId)->where('role', 'worker')->first();

        if (!$worker) {
            return response()->json(['message' => 'Worker not found'], 404);
        }

        $date = $request->query('date') ? Carbon::parse($request->query('date')) : Carbon::today();
        $weekStart = $date->copy()->startOfWeek();

        $summary = WorkSummary::where('worker_id', $workerId)
            ->where('period_type', 'weekly')
            ->whereDate('period_start', $weekStart)
            ->first();

        if (!$summary) {
            $summary = $this->summaryService->calculateWeekly($worker, $weekStart);
        }

        return response()->json([
            'worker' => [
                'id' => $worker->id,
                'name' => $worker->name,
            ],
            'period' => 'weekly',
            'week_start' => $summary->period_start->format('Y-m-d'),
            'week_end' => $summary->period_end->format('Y-m-d'),
            'summary' => [
                'total_hours' => $summary->total_hours,
                'total_minutes' => $summary->total_minutes,
                'regular_hours' => $summary->regular_hours,
                'overtime_hours' => $summary->overtime_hours,
                'formatted_time' => $summary->formatted_total_time,
                'days_worked' => $summary->days_worked,
                'days_absent' => $summary->days_absent,
                'late_arrivals' => $summary->late_arrivals,
                'early_departures' => $summary->early_departures,
                'missing_checkouts' => $summary->missing_checkouts,
                'missing_checkins' => $summary->missing_checkins,
            ],
            'calculated_at' => $summary->calculated_at?->toIso8601String(),
        ]);
    }

    public function monthlySummary(Request $request, int $workerId): JsonResponse
    {
        $user = $request->user();

        if (!$this->canViewWorkerReports($user, $workerId)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $worker = User::where('id', $workerId)->where('role', 'worker')->first();

        if (!$worker) {
            return response()->json(['message' => 'Worker not found'], 404);
        }

        // Accept month parameter (YYYY-MM) or date parameter
        if ($request->query('month')) {
            $monthStart = Carbon::createFromFormat('Y-m', $request->query('month'))->startOfMonth();
        } elseif ($request->query('date')) {
            $monthStart = Carbon::parse($request->query('date'))->startOfMonth();
        } else {
            $monthStart = Carbon::today()->startOfMonth();
        }

        $summary = WorkSummary::where('worker_id', $workerId)
            ->where('period_type', 'monthly')
            ->whereDate('period_start', $monthStart)
            ->first();

        if (!$summary) {
            $summary = $this->summaryService->calculateMonthly($worker, $monthStart);
        }

        return response()->json([
            'worker' => [
                'id' => $worker->id,
                'name' => $worker->name,
            ],
            'period' => 'monthly',
            'month' => $monthStart->format('Y-m'),
            'month_start' => $summary->period_start->format('Y-m-d'),
            'month_end' => $summary->period_end->format('Y-m-d'),
            'summary' => [
                'total_hours' => $summary->total_hours,
                'total_minutes' => $summary->total_minutes,
                'regular_hours' => $summary->regular_hours,
                'overtime_hours' => $summary->overtime_hours,
                'formatted_time' => $summary->formatted_total_time,
                'days_worked' => $summary->days_worked,
                'days_absent' => $summary->days_absent,
                'late_arrivals' => $summary->late_arrivals,
                'early_departures' => $summary->early_departures,
                'missing_checkouts' => $summary->missing_checkouts,
                'missing_checkins' => $summary->missing_checkins,
            ],
            'calculated_at' => $summary->calculated_at?->toIso8601String(),
        ]);
    }

    public function yearlySummary(Request $request, int $workerId): JsonResponse
    {
        $user = $request->user();

        if (!$this->canViewWorkerReports($user, $workerId)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $worker = User::where('id', $workerId)->where('role', 'worker')->first();

        if (!$worker) {
            return response()->json(['message' => 'Worker not found'], 404);
        }

        $year = (int) $request->query('year', date('Y'));

        $summary = WorkSummary::where('worker_id', $workerId)
            ->where('period_type', 'yearly')
            ->whereYear('period_start', $year)
            ->first();

        if (!$summary) {
            $summary = $this->summaryService->calculateYearly($worker, $year);
        }

        return response()->json([
            'worker' => [
                'id' => $worker->id,
                'name' => $worker->name,
            ],
            'period' => 'yearly',
            'year' => $year,
            'year_start' => $summary->period_start->format('Y-m-d'),
            'year_end' => $summary->period_end->format('Y-m-d'),
            'summary' => [
                'total_hours' => $summary->total_hours,
                'total_minutes' => $summary->total_minutes,
                'regular_hours' => $summary->regular_hours,
                'overtime_hours' => $summary->overtime_hours,
                'formatted_time' => $summary->formatted_total_time,
                'days_worked' => $summary->days_worked,
                'days_absent' => $summary->days_absent,
                'late_arrivals' => $summary->late_arrivals,
                'early_departures' => $summary->early_departures,
                'missing_checkouts' => $summary->missing_checkouts,
                'missing_checkins' => $summary->missing_checkins,
            ],
            'calculated_at' => $summary->calculated_at?->toIso8601String(),
        ]);
    }

    public function workerLogs(Request $request, int $workerId): JsonResponse
    {
        $user = $request->user();

        if (!$this->canViewWorkerReports($user, $workerId)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $worker = User::where('id', $workerId)->where('role', 'worker')->first();

        if (!$worker) {
            return response()->json(['message' => 'Worker not found'], 404);
        }

        $from = $request->query('from') ? Carbon::parse($request->query('from')) : Carbon::today()->startOfMonth();
        $to = $request->query('to') ? Carbon::parse($request->query('to')) : Carbon::today();

        // Get all logs in the period
        $logs = AttendanceLog::where('worker_id', $workerId)
            ->whereBetween('device_time', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('device_time', 'asc')
            ->get();

        // Group logs by date and calculate daily summaries
        $dailyData = $logs->groupBy(fn ($log) => $log->device_time->format('Y-m-d'))
            ->map(function ($dayLogs, $date) {
                $checkouts = $dayLogs->where('type', 'out')->whereNotNull('work_minutes');
                $checkins = $dayLogs->where('type', 'in');

                $totalMinutes = (int) $checkouts->sum('work_minutes');
                $overtimeMinutes = (int) $checkouts->where('is_overtime', true)->sum('overtime_minutes');
                $regularMinutes = max(0, $totalMinutes - $overtimeMinutes);

                return [
                    'date' => $date,
                    'summary' => [
                        'total_hours' => round($totalMinutes / 60, 2),
                        'total_minutes' => $totalMinutes,
                        'regular_hours' => round($regularMinutes / 60, 2),
                        'overtime_hours' => round($overtimeMinutes / 60, 2),
                        'formatted_time' => sprintf('%d:%02d', intdiv($totalMinutes, 60), $totalMinutes % 60),
                        'late_arrivals' => $checkins->where('is_late', true)->count(),
                        'early_departures' => $checkouts->where('is_early_departure', true)->count(),
                        'missing_checkouts' => $checkins->whereNull('paired_log_id')->count(),
                        'missing_checkins' => $dayLogs->where('type', 'out')->whereNull('paired_log_id')->count(),
                    ],
                    'logs' => AttendanceLogResource::collection($dayLogs->sortByDesc('device_time')->values()),
                ];
            })
            ->sortByDesc('date')
            ->values();

        // Calculate period summary
        $allCheckouts = $logs->where('type', 'out')->whereNotNull('work_minutes');
        $allCheckins = $logs->where('type', 'in');

        $periodTotalMinutes = (int) $allCheckouts->sum('work_minutes');
        $periodOvertimeMinutes = (int) $allCheckouts->where('is_overtime', true)->sum('overtime_minutes');
        $periodRegularMinutes = max(0, $periodTotalMinutes - $periodOvertimeMinutes);

        return response()->json([
            'worker' => [
                'id' => $worker->id,
                'name' => $worker->name,
            ],
            'period' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
            ],
            'summary' => [
                'total_hours' => round($periodTotalMinutes / 60, 2),
                'total_minutes' => $periodTotalMinutes,
                'regular_hours' => round($periodRegularMinutes / 60, 2),
                'overtime_hours' => round($periodOvertimeMinutes / 60, 2),
                'formatted_time' => sprintf('%d:%02d', intdiv($periodTotalMinutes, 60), $periodTotalMinutes % 60),
                'days_worked' => $dailyData->count(),
                'late_arrivals' => $allCheckins->where('is_late', true)->count(),
                'early_departures' => $allCheckouts->where('is_early_departure', true)->count(),
                'missing_checkouts' => $allCheckins->whereNull('paired_log_id')->count(),
                'missing_checkins' => $logs->where('type', 'out')->whereNull('paired_log_id')->count(),
            ],
            'total_days' => $dailyData->count(),
            'total_logs' => $logs->count(),
            'days' => $dailyData,
        ]);
    }

    public function flaggedLogs(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $logs = AttendanceLog::where('flagged', true)
            ->with(['worker:id,name,employee_id', 'representative:id,name'])
            ->orderBy('device_time', 'desc')
            ->paginate(50);

        return response()->json([
            'total' => $logs->total(),
            'per_page' => $logs->perPage(),
            'current_page' => $logs->currentPage(),
            'last_page' => $logs->lastPage(),
            'logs' => $logs->items(),
        ]);
    }

    public function allWorkersDailySummary(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isRepresentative()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $date = $request->query('date') ? Carbon::parse($request->query('date')) : Carbon::today();
        $perPage = $request->query('per_page', 50);

        $workers = User::where('role', 'worker')
            ->where('status', 'active')
            ->select(['id', 'name', 'employee_id'])
            ->paginate($perPage);

        $workerIds = $workers->pluck('id')->toArray();

        // Aggregate check-out stats from attendance_logs
        $checkoutStats = AttendanceLog::whereIn('worker_id', $workerIds)
            ->where('type', 'out')
            ->whereNotNull('work_minutes')
            ->whereDate('device_time', $date)
            ->selectRaw('
                worker_id,
                SUM(work_minutes) as total_minutes,
                SUM(CASE WHEN is_overtime = 1 THEN overtime_minutes ELSE 0 END) as overtime_minutes,
                SUM(CASE WHEN is_early_departure = 1 THEN 1 ELSE 0 END) as early_departures
            ')
            ->groupBy('worker_id')
            ->get()
            ->keyBy('worker_id');

        // Aggregate check-in stats from attendance_logs
        $checkinStats = AttendanceLog::whereIn('worker_id', $workerIds)
            ->where('type', 'in')
            ->whereDate('device_time', $date)
            ->selectRaw('
                worker_id,
                SUM(CASE WHEN is_late = 1 THEN 1 ELSE 0 END) as late_arrivals
            ')
            ->groupBy('worker_id')
            ->get()
            ->keyBy('worker_id');

        $result = $workers->getCollection()->map(function ($worker) use ($checkoutStats, $checkinStats) {
            $checkout = $checkoutStats->get($worker->id);
            $checkin = $checkinStats->get($worker->id);

            $totalMinutes = (int) ($checkout->total_minutes ?? 0);
            $overtimeMinutes = (int) ($checkout->overtime_minutes ?? 0);
            $regularMinutes = max(0, $totalMinutes - $overtimeMinutes);

            return [
                'id' => $worker->id,
                'name' => $worker->name,
                'employee_id' => $worker->employee_id,
                'total_hours' => round($totalMinutes / 60, 2),
                'total_minutes' => $totalMinutes,
                'regular_hours' => round($regularMinutes / 60, 2),
                'overtime_hours' => round($overtimeMinutes / 60, 2),
                'formatted_time' => sprintf('%d:%02d', intdiv($totalMinutes, 60), $totalMinutes % 60),
                'late_arrivals' => (int) ($checkin->late_arrivals ?? 0),
                'early_departures' => (int) ($checkout->early_departures ?? 0),
            ];
        });

        return response()->json([
            'period' => 'daily',
            'date' => $date->format('Y-m-d'),
            'total' => $workers->total(),
            'per_page' => $workers->perPage(),
            'current_page' => $workers->currentPage(),
            'last_page' => $workers->lastPage(),
            'workers' => $result,
        ]);
    }

    public function allWorkersWeeklySummary(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isRepresentative()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $date = $request->query('date') ? Carbon::parse($request->query('date')) : Carbon::today();
        $weekStart = $date->copy()->startOfWeek();
        $perPage = $request->query('per_page', 50);

        $workers = User::where('role', 'worker')
            ->where('status', 'active')
            ->select(['id', 'name', 'employee_id'])
            ->paginate($perPage);

        $workerIds = $workers->pluck('id')->toArray();

        $summaries = WorkSummary::where('period_type', 'weekly')
            ->whereDate('period_start', $weekStart)
            ->whereIn('worker_id', $workerIds)
            ->get()
            ->keyBy('worker_id');

        $result = $workers->getCollection()->map(function ($worker) use ($summaries, $weekStart) {
            $summary = $summaries->get($worker->id);

            if (!$summary) {
                $summary = $this->summaryService->calculateWeekly($worker, $weekStart->copy());
            }

            return [
                'id' => $worker->id,
                'name' => $worker->name,
                'employee_id' => $worker->employee_id,
                'total_hours' => $summary->total_hours,
                'total_minutes' => $summary->total_minutes,
                'formatted_time' => $summary->formatted_total_time,
                'days_worked' => $summary->days_worked,
                'days_absent' => $summary->days_absent,
                'late_arrivals' => $summary->late_arrivals,
                'early_departures' => $summary->early_departures,
            ];
        });

        return response()->json([
            'period' => 'weekly',
            'week_start' => $weekStart->format('Y-m-d'),
            'week_end' => $weekStart->copy()->endOfWeek()->format('Y-m-d'),
            'total' => $workers->total(),
            'per_page' => $workers->perPage(),
            'current_page' => $workers->currentPage(),
            'last_page' => $workers->lastPage(),
            'workers' => $result,
        ]);
    }

    public function allWorkersMonthlySummary(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isRepresentative()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Accept month parameter (YYYY-MM) or date parameter
        if ($request->query('month')) {
            $monthStart = Carbon::createFromFormat('Y-m', $request->query('month'))->startOfMonth();
        } elseif ($request->query('date')) {
            $monthStart = Carbon::parse($request->query('date'))->startOfMonth();
        } else {
            $monthStart = Carbon::today()->startOfMonth();
        }
        $perPage = $request->query('per_page', 50);

        $workers = User::where('role', 'worker')
            ->where('status', 'active')
            ->select(['id', 'name', 'employee_id'])
            ->paginate($perPage);

        $workerIds = $workers->pluck('id')->toArray();

        $summaries = WorkSummary::where('period_type', 'monthly')
            ->whereDate('period_start', $monthStart)
            ->whereIn('worker_id', $workerIds)
            ->get()
            ->keyBy('worker_id');

        $result = $workers->getCollection()->map(function ($worker) use ($summaries, $monthStart) {
            $summary = $summaries->get($worker->id);

            if (!$summary) {
                $summary = $this->summaryService->calculateMonthly($worker, $monthStart->copy());
            }

            return [
                'id' => $worker->id,
                'name' => $worker->name,
                'employee_id' => $worker->employee_id,
                'total_hours' => $summary->total_hours,
                'total_minutes' => $summary->total_minutes,
                'formatted_time' => $summary->formatted_total_time,
                'days_worked' => $summary->days_worked,
                'days_absent' => $summary->days_absent,
                'late_arrivals' => $summary->late_arrivals,
                'early_departures' => $summary->early_departures,
                'overtime_hours' => $summary->overtime_hours,
            ];
        });

        return response()->json([
            'period' => 'monthly',
            'month' => $monthStart->format('Y-m'),
            'month_start' => $monthStart->format('Y-m-d'),
            'month_end' => $monthStart->copy()->endOfMonth()->format('Y-m-d'),
            'total' => $workers->total(),
            'per_page' => $workers->perPage(),
            'current_page' => $workers->currentPage(),
            'last_page' => $workers->lastPage(),
            'workers' => $result,
        ]);
    }

    public function allWorkersYearlySummary(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin() && !$user->isRepresentative()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $year = $request->query('year', date('Y'));
        $perPage = $request->query('per_page', 50);
        $calculate = $request->boolean('calculate', false);

        // Get all active workers
        $allWorkerIds = User::where('role', 'worker')
            ->where('status', 'active')
            ->pluck('id')
            ->toArray();

        // Calculate max month for the year
        $currentYear = (int) date('Y');
        $maxMonth = ($year < $currentYear) ? 12 : (int) date('n');
        $expectedMonthsPerWorker = $maxMonth;
        $totalExpectedSummaries = count($allWorkerIds) * $expectedMonthsPerWorker;

        // Get existing monthly summaries for this year
        $existingSummaries = WorkSummary::where('period_type', 'monthly')
            ->whereYear('period_start', $year)
            ->whereIn('worker_id', $allWorkerIds)
            ->get()
            ->groupBy(function ($item) {
                return $item->worker_id . '_' . $item->period_start->format('Y-m');
            });

        // Find missing summaries
        $missingJobs = [];
        foreach ($allWorkerIds as $workerId) {
            for ($m = 1; $m <= $maxMonth; $m++) {
                $monthKey = $workerId . '_' . $year . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
                if (!$existingSummaries->has($monthKey)) {
                    $missingJobs[] = [
                        'worker_id' => $workerId,
                        'month' => $year . '-' . str_pad($m, 2, '0', STR_PAD_LEFT),
                    ];
                }
            }
        }

        $missingMonthlySummariesCount = count($missingJobs);

        // Check for missing yearly summaries
        $existingYearlySummaries = WorkSummary::where('period_type', 'yearly')
            ->whereYear('period_start', $year)
            ->whereIn('worker_id', $allWorkerIds)
            ->pluck('worker_id')
            ->toArray();

        $missingYearlyWorkerIds = array_diff($allWorkerIds, $existingYearlySummaries);
        $missingYearlySummariesCount = count($missingYearlyWorkerIds);

        // If calculate=true, queue jobs for missing summaries
        if ($calculate && ($missingMonthlySummariesCount > 0 || $missingYearlySummariesCount > 0)) {
            // Check if there are pending jobs for work-summary calculations
            $pendingJobsCount = DB::table('jobs')
                ->where('queue', 'default')
                ->where('payload', 'like', '%CalculateWorkSummary%')
                ->count();

            if ($pendingJobsCount > 0) {
                return response()->json([
                    'message' => 'Calculation already in progress',
                    'year' => $year,
                    'pending_jobs' => $pendingJobsCount,
                    'hint' => 'Please wait for the current calculation to complete',
                ], 409); // 409 Conflict
            }

            // Dispatch monthly jobs first
            foreach ($missingJobs as $job) {
                CalculateWorkSummary::dispatch(
                    $job['worker_id'],
                    $job['month'] . '-01',
                    'monthly'
                );
            }

            // Dispatch yearly jobs with delay (after monthly jobs complete)
            $delaySeconds = max(5, $missingMonthlySummariesCount * 0.2); // ~200ms per job estimate
            foreach ($missingYearlyWorkerIds as $workerId) {
                CalculateWorkSummary::dispatch(
                    $workerId,
                    $year . '-01-01',
                    'yearly'
                )->delay(now()->addSeconds($delaySeconds));
            }

            return response()->json([
                'message' => 'Calculation jobs queued',
                'year' => $year,
                'queued_monthly_jobs' => $missingMonthlySummariesCount,
                'queued_yearly_jobs' => $missingYearlySummariesCount,
                'total_workers' => count($allWorkerIds),
                'expected_summaries' => $totalExpectedSummaries,
                'existing_summaries' => $totalExpectedSummaries - $missingMonthlySummariesCount,
            ], 202);
        }

        // Paginate workers for response
        $workersPagination = User::where('role', 'worker')
            ->where('status', 'active')
            ->select(['id', 'name', 'employee_id'])
            ->paginate($perPage);

        $workerIds = $workersPagination->pluck('id')->toArray();

        // Get existing summaries for paginated workers only
        $paginatedSummaries = WorkSummary::where('period_type', 'monthly')
            ->whereYear('period_start', $year)
            ->whereIn('worker_id', $workerIds)
            ->get()
            ->groupBy(function ($item) {
                return $item->worker_id . '_' . $item->period_start->format('Y-m');
            });

        $result = collect();

        foreach ($workersPagination->getCollection() as $worker) {
            for ($m = 1; $m <= $maxMonth; $m++) {
                $monthStr = $year . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
                $monthKey = $worker->id . '_' . $monthStr;

                $summary = $paginatedSummaries->get($monthKey)?->first();

                if ($summary) {
                    $result->push([
                        'id' => $worker->id,
                        'name' => $worker->name,
                        'employee_id' => $worker->employee_id,
                        'month' => $monthStr,
                        'total_hours' => $summary->total_hours,
                        'total_minutes' => $summary->total_minutes,
                        'regular_hours' => $summary->regular_hours,
                        'overtime_hours' => $summary->overtime_hours,
                        'formatted_time' => $summary->formatted_total_time,
                        'days_worked' => $summary->days_worked,
                        'days_absent' => $summary->days_absent,
                        'late_arrivals' => $summary->late_arrivals,
                        'early_departures' => $summary->early_departures,
                        'missing_checkouts' => $summary->missing_checkouts,
                        'missing_checkins' => $summary->missing_checkins,
                        'status' => 'calculated',
                    ]);
                } else {
                    // Return placeholder for missing data
                    $result->push([
                        'id' => $worker->id,
                        'name' => $worker->name,
                        'employee_id' => $worker->employee_id,
                        'month' => $monthStr,
                        'status' => 'pending',
                    ]);
                }
            }
        }

        $totalMissing = $missingMonthlySummariesCount + $missingYearlySummariesCount;

        return response()->json([
            'period' => 'yearly',
            'year' => $year,
            'total_workers' => $workersPagination->total(),
            'per_page' => $workersPagination->perPage(),
            'current_page' => $workersPagination->currentPage(),
            'last_page' => $workersPagination->lastPage(),
            'missing_monthly_summaries' => $missingMonthlySummariesCount,
            'missing_yearly_summaries' => $missingYearlySummariesCount,
            'hint' => $totalMissing > 0 ? 'Use calculate=true to queue calculation jobs' : null,
            'data' => $result,
        ]);
    }

    private function canViewWorkerReports(User $user, int $workerId): bool
    {
        // Admins and representatives can view any worker
        if ($user->isAdmin() || $user->isRepresentative()) {
            return true;
        }

        // Workers can only view their own reports
        return $user->id === $workerId;
    }
}
