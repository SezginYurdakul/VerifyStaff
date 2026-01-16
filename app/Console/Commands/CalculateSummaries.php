<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WorkSummary;
use App\Services\WorkSummaryService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CalculateSummaries extends Command
{
    protected $signature = 'summaries:calculate
                            {--period=all : Period type: daily, weekly, monthly, yearly or all}
                            {--date= : Specific date (YYYY-MM-DD) to calculate for}
                            {--month= : Specific month (YYYY-MM) for monthly calculation}
                            {--year= : Specific year (YYYY) for yearly calculation (processes each month)}
                            {--force : Recalculate even if summary exists}';

    protected $description = 'Pre-calculate work summaries for all workers';

    public function __construct(
        private WorkSummaryService $summaryService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $period = $this->option('period');
        $force = $this->option('force');

        $workers = User::where('role', 'worker')
            ->where('status', 'active')
            ->get();

        if ($workers->isEmpty()) {
            $this->warn('No active workers found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$workers->count()} active workers.");

        $periods = $period === 'all'
            ? ['daily', 'weekly', 'monthly','yearly']
            : [$period];

        foreach ($periods as $p) {
            $this->calculatePeriod($workers, $p, $force);
        }

        $this->info('Summary calculation completed!');
        return Command::SUCCESS;
    }

    private function calculatePeriod($workers, string $period, bool $force): void
    {
        $this->info("Calculating {$period} summaries...");

        if ($period === 'yearly') {
            $this->calculateYearlyByMonths($workers, $force);
            return;
        }

        $date = $this->getDateForPeriod($period);
        $bar = $this->output->createProgressBar($workers->count());
        $bar->start();

        $calculated = 0;
        $skipped = 0;

        foreach ($workers as $worker) {
            $exists = $this->summaryExists($worker->id, $period, $date);

            if ($exists && !$force) {
                $skipped++;
                $bar->advance();
                continue;
            }

            $this->runServiceCalculation($worker, $period, $date);

            $calculated++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("  → Calculated: {$calculated}, Skipped: {$skipped}");
    }

    private function calculateYearlyByMonths($workers, bool $force): void
    {
        $year = $this->option('year') ?: date('Y');
        $currentYear = date('Y');
        $maxMonth = ($year < $currentYear) ? 12 : date('n');

        $this->info("Processing {$year} month by month (up to month {$maxMonth})...");

        foreach (range(1, $maxMonth) as $month) {
            $monthDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $this->comment("\nProcessing Month: {$monthDate->format('Y-m')}");

            $bar = $this->output->createProgressBar($workers->count());
            $bar->start();

            foreach ($workers as $worker) {
                $exists = $this->summaryExists($worker->id, 'monthly', $monthDate);

                if ($exists && !$force) {
                    $bar->advance();
                    continue;
                }

                $this->summaryService->calculateMonthly($worker, $monthDate->copy());
                $bar->advance();
            }
            $bar->finish();
        }
        $this->newLine();
    }

    private function runServiceCalculation($worker, string $period, Carbon $date): void
    {
        match ($period) {
            'daily' => $this->summaryService->calculateDaily($worker, $date->copy()),
            'weekly' => $this->summaryService->calculateWeekly($worker, $date->copy()->startOfWeek()),
            'monthly' => $this->summaryService->calculateMonthly($worker, $date->copy()->startOfMonth()),
        };
    }

    private function getDateForPeriod(string $period): Carbon
    {
        if ($this->option('date')) {
            return Carbon::parse($this->option('date'));
        }

        if ($period === 'monthly' && $this->option('month')) {
            return Carbon::createFromFormat('Y-m', $this->option('month'))->startOfMonth();
        }

        return match ($period) {
            'daily' => Carbon::yesterday(),
            'weekly' => Carbon::today(),
            'monthly' => Carbon::today(),
            default => Carbon::today(),
        };
    }

    private function summaryExists(int $workerId, string $period, Carbon $date): bool
    {
        $query = WorkSummary::where('worker_id', $workerId)
            ->where('period_type', $period);

        return match ($period) {
            'daily' => $query->whereDate('period_start', $date)->exists(),
            'weekly' => $query->whereDate('period_start', $date->copy()->startOfWeek())->exists(),
            'monthly' => $query->whereDate('period_start', $date->copy()->startOfMonth())->exists(),
            default => false
        };
    }
}