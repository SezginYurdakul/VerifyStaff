<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceLogResource;
use App\Models\AttendanceLog;
use App\Models\User;
use App\Models\WorkSummary;
use App\Services\WorkSummaryService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function __construct(
        private WorkSummaryService $summaryService
    ) {}

    public function dailySummary(Request $request, int $workerId): JsonResponse
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

        $summary = WorkSummary::where('worker_id', $workerId)
            ->where('period_type', 'daily')
            ->whereDate('period_start', $date)
            ->first();

        if (!$summary) {
            $summary = $this->summaryService->calculateDaily($worker, $date);
        }

        return response()->json([
            'worker' => [
                'id' => $worker->id,
                'name' => $worker->name,
            ],
            'period' => 'daily',
            'date' => $date->format('Y-m-d'),
            'summary' => [
                'total_hours' => $summary->total_hours,
                'total_minutes' => $summary->total_minutes,
                'regular_hours' => $summary->regular_hours,
                'overtime_hours' => $summary->overtime_hours,
                'formatted_time' => $summary->formatted_total_time,
                'late_arrivals' => $summary->late_arrivals,
                'early_departures' => $summary->early_departures,
                'missing_checkouts' => $summary->missing_checkouts,
                'missing_checkins' => $summary->missing_checkins,
            ],
            'calculated_at' => $summary->calculated_at?->toIso8601String(),
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

        $logs = AttendanceLog::where('worker_id', $workerId)
            ->whereBetween('device_time', [$from->startOfDay(), $to->endOfDay()])
            ->orderBy('device_time', 'desc')
            ->get();

        return response()->json([
            'worker' => [
                'id' => $worker->id,
                'name' => $worker->name,
            ],
            'period' => [
                'from' => $from->format('Y-m-d'),
                'to' => $to->format('Y-m-d'),
            ],
            'total' => $logs->count(),
            'logs' => AttendanceLogResource::collection($logs),
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

        $summaries = WorkSummary::where('period_type', 'daily')
            ->whereDate('period_start', $date)
            ->whereIn('worker_id', $workerIds)
            ->get()
            ->keyBy('worker_id');

        $result = $workers->getCollection()->map(function ($worker) use ($summaries, $date) {
            $summary = $summaries->get($worker->id);

            if (!$summary) {
                $summary = $this->summaryService->calculateDaily($worker, $date->copy());
            }

            return [
                'id' => $worker->id,
                'name' => $worker->name,
                'employee_id' => $worker->employee_id,
                'total_hours' => $summary->total_hours,
                'total_minutes' => $summary->total_minutes,
                'formatted_time' => $summary->formatted_total_time,
                'late_arrivals' => $summary->late_arrivals,
                'early_departures' => $summary->early_departures,
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

        // 1. Yetki Kontrolü
        if (!$user->isAdmin() && !$user->isRepresentative()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // 2. Parametreleri Hazırla
        $year = $request->query('year', date('Y'));
        $perPage = $request->query('per_page', 50);

        // 3. İşçileri Sayfala (Döngü bu işçiler üzerinden dönecek)
        $workersPagination = User::where('role', 'worker')
            ->where('status', 'active')
            ->select(['id', 'name', 'employee_id'])
            ->paginate($perPage);

        $workerIds = $workersPagination->pluck('id')->toArray();

        // Yılın kaçıncı ayına kadar döküm alınacak (Gelecek ayları hesaplamamak için)
        $currentYear = date('Y');
        $maxMonth = ($year < $currentYear) ? 12 : date('n');

        $result = collect();

        // 4. Performans için mevcut aylık özetleri tek seferde çek
        $existingSummaries = WorkSummary::where('period_type', 'monthly')
            ->whereYear('period_start', $year)
            ->whereIn('worker_id', $workerIds)
            ->get()
            ->groupBy(function ($item) {
                return $item->worker_id . '_' . $item->period_start->format('Y-m');
            });

        // 5. İşçi ve Ay Döngüsü
        foreach ($workersPagination->getCollection() as $worker) {
            for ($m = 1; $m <= $maxMonth; $m++) {
                $monthStart = Carbon::createFromDate($year, $m, 1)->startOfMonth();
                $monthKey = $worker->id . '_' . $monthStart->format('Y-m');

                // Kayıt var mı kontrol et
                $summary = $existingSummaries->has($monthKey)
                    ? $existingSummaries->get($monthKey)->first()
                    : null;

                // 6. Kayıt yoksa Service üzerinden hesapla ve DB'ye işle
                if (!$summary) {
                    // Bu çağrı Service içinde WorkSummary modelini create/save edecektir
                    $summary = $this->summaryService->calculateMonthly($worker, $monthStart->copy());
                }

                // 7. Tekil Ay Verisini Push Et (allWorkersMonthlySummary formatı ile birebir aynı)
                $result->push([
                    'id' => $worker->id,
                    'name' => $worker->name,
                    'employee_id' => $worker->employee_id,
                    'month' => $monthStart->format('Y-m'),
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
                ]);
            }
        }

        // 8. Sonucu Döndür
        return response()->json([
            'period' => 'yearly',
            'year' => $year,
            'total_workers' => $workersPagination->total(),
            'per_page' => $workersPagination->perPage(),
            'current_page' => $workersPagination->currentPage(),
            'last_page' => $workersPagination->lastPage(),
            'data' => $result, // Liste içeriği
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
