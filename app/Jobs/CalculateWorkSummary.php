<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\WorkSummaryService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CalculateWorkSummary implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $workerId,
        public string $date,
        public string $periodType = 'weekly'
    ) {}

    /**
     * Unique job identifier to prevent duplicate jobs in queue.
     */
    public function uniqueId(): string
    {
        return "{$this->workerId}:{$this->periodType}:{$this->date}";
    }

    /**
     * How long the unique lock should be held (until end of the period day).
     */
    public function uniqueFor(): int
    {
        $endOfDay = Carbon::parse($this->date)->endOfDay();
        $seconds = $endOfDay->diffInSeconds(now());

        // Minimum 60 seconds, maximum 1 day
        return max(60, min($seconds, 86400));
    }

    public function handle(WorkSummaryService $service): void
    {
        $worker = User::find($this->workerId);

        if (!$worker) {
            Log::warning('CalculateWorkSummary: Worker not found', [
                'worker_id' => $this->workerId,
                'date' => $this->date,
                'period_type' => $this->periodType,
            ]);
            return;
        }

        $date = Carbon::parse($this->date);

        match ($this->periodType) {
            'weekly' => $service->calculateWeekly($worker, $date->startOfWeek()),
            'monthly' => $service->calculateMonthly($worker, $date->startOfMonth()),
            'yearly' => $service->calculateYearly($worker, $date->year),
            default => null, // daily no longer supported
        };

        Log::info('CalculateWorkSummary: Completed', [
            'worker_id' => $this->workerId,
            'date' => $this->date,
            'period_type' => $this->periodType,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('CalculateWorkSummary: Job failed permanently', [
            'worker_id' => $this->workerId,
            'date' => $this->date,
            'period_type' => $this->periodType,
            'error' => $exception?->getMessage(),
            'trace' => $exception?->getTraceAsString(),
        ]);
    }

    public function tags(): array
    {
        return ['work-summary', 'worker:' . $this->workerId, $this->periodType];
    }
}
