<?php

namespace App\Services\Dashboard;

use App\Models\AttendanceLog;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class DashboardTrendsService
{
    public function getTrends(int $days = 7): array
    {
        $days = min(max($days, 7), 90);

        $endDate = Carbon::today();
        $startDate = $endDate->copy()->subDays($days - 1);

        $dailyData = $this->getDailyTrendData($startDate, $endDate);
        $activeWorkers = $this->getActiveWorkersCount();

        $chartData = $this->formatChartData($startDate, $endDate, $dailyData, $activeWorkers);
        $averages = $this->calculateAverages($chartData);

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
                'days' => $days,
            ],
            'averages' => $averages,
            'data' => $chartData,
        ];
    }

    private function getActiveWorkersCount(): int
    {
        return User::where('role', 'worker')
            ->where('status', 'active')
            ->count();
    }

    private function getDailyTrendData(Carbon $startDate, Carbon $endDate): Collection
    {
        $checkins = AttendanceLog::where('type', 'in')
            ->whereBetween('device_time', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->selectRaw('DATE(device_time) as date, COUNT(DISTINCT worker_id) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $checkoutStats = AttendanceLog::where('type', 'out')
            ->whereBetween('device_time', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->selectRaw('
                DATE(device_time) as date,
                COUNT(DISTINCT worker_id) as count,
                SUM(work_minutes) as total_minutes
            ')
            ->groupBy('date')
            ->get()
            ->keyBy('date');

        $lateArrivals = AttendanceLog::where('type', 'in')
            ->where('is_late', true)
            ->whereBetween('device_time', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->selectRaw('DATE(device_time) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $earlyDepartures = AttendanceLog::where('type', 'out')
            ->where('is_early_departure', true)
            ->whereBetween('device_time', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->selectRaw('DATE(device_time) as date, COUNT(*) as count')
            ->groupBy('date')
            ->pluck('count', 'date');

        $result = collect();
        $period = CarbonPeriod::create($startDate, $endDate);

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $checkout = $checkoutStats->get($dateStr);

            $result->put($dateStr, [
                'checkins' => $checkins->get($dateStr, 0),
                'checkouts' => $checkout?->count ?? 0,
                'total_minutes' => $checkout?->total_minutes ?? 0,
                'late_arrivals' => $lateArrivals->get($dateStr, 0),
                'early_departures' => $earlyDepartures->get($dateStr, 0),
            ]);
        }

        return $result;
    }

    private function formatChartData(
        Carbon $startDate,
        Carbon $endDate,
        Collection $dailyData,
        int $activeWorkers
    ): array {
        $chartData = [];
        $period = CarbonPeriod::create($startDate, $endDate);

        foreach ($period as $date) {
            $dateStr = $date->format('Y-m-d');
            $dayData = $dailyData->get($dateStr);

            $chartData[] = [
                'date' => $dateStr,
                'day' => $date->format('D'),
                'checkins' => $dayData['checkins'] ?? 0,
                'checkouts' => $dayData['checkouts'] ?? 0,
                'total_hours' => round(($dayData['total_minutes'] ?? 0) / 60, 1),
                'late_arrivals' => $dayData['late_arrivals'] ?? 0,
                'early_departures' => $dayData['early_departures'] ?? 0,
                'attendance_rate' => $activeWorkers > 0
                    ? round((($dayData['checkins'] ?? 0) / $activeWorkers) * 100, 1)
                    : 0,
            ];
        }

        return $chartData;
    }

    private function calculateAverages(array $chartData): array
    {
        $totalDays = count($chartData);

        if ($totalDays === 0) {
            return [
                'daily_checkins' => 0,
                'daily_hours' => 0,
                'attendance_rate' => 0,
            ];
        }

        $collection = collect($chartData);

        return [
            'daily_checkins' => round($collection->avg('checkins'), 1),
            'daily_hours' => round($collection->avg('total_hours'), 1),
            'attendance_rate' => round($collection->avg('attendance_rate'), 1),
        ];
    }
}
