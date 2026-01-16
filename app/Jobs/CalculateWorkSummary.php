<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\WorkSummaryService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CalculateWorkSummary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public int $workerId,
        public string $date,
        public string $periodType = 'daily'
    ) {}

    public function handle(WorkSummaryService $service): void
    {
        $worker = User::find($this->workerId);

        if (!$worker) {
            return;
        }

        $date = Carbon::parse($this->date);

        match ($this->periodType) {
            'daily' => $service->calculateDaily($worker, $date),
            'weekly' => $service->calculateWeekly($worker, $date->startOfWeek()),
            'monthly' => $service->calculateMonthly($worker, $date->startOfMonth()),
        };
    }

    public function tags(): array
    {
        return ['work-summary', 'worker:' . $this->workerId, $this->periodType];
    }
}
