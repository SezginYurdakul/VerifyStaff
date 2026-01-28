<?php

namespace App\Observers;

use App\Models\AttendanceLog;
use App\Models\WorkSummary;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Auth;

class AttendanceLogObserver
{
    /**
     * Handle the AttendanceLog "created" event.
     */
    public function created(AttendanceLog $log): void
    {
        $this->markRelatedSummariesDirty($log);

        AuditLogger::attendance(
            action: $log->type === 'in' ? 'check_in' : 'check_out',
            workerId: $log->worker_id,
            data: [
                'log_id' => $log->id,
                'device_time' => $log->device_time?->toIso8601String(),
                'sync_id' => $log->sync_id,
                'flagged' => $log->flagged,
                'paired_log_id' => $log->paired_log_id,
                'work_minutes' => $log->work_minutes,
            ],
            performedBy: $log->representative_id
        );
    }

    /**
     * Handle the AttendanceLog "updated" event.
     */
    public function updated(AttendanceLog $log): void
    {
        $changes = $log->getChanges();

        // Only log significant changes
        $significantFields = ['flagged', 'paired_log_id', 'work_minutes', 'is_late', 'is_early_departure'];
        $hasSignificantChange = !empty(array_intersect(array_keys($changes), $significantFields));

        // Mark summaries dirty if summary-affecting fields changed
        $summaryAffectingFields = ['work_minutes', 'is_late', 'is_early_departure', 'is_overtime', 'overtime_minutes', 'paired_log_id', 'device_time'];
        if (!empty(array_intersect(array_keys($changes), $summaryAffectingFields))) {
            $this->markRelatedSummariesDirty($log);
        }

        if ($hasSignificantChange) {
            AuditLogger::attendance(
                action: 'updated',
                workerId: $log->worker_id,
                data: [
                    'log_id' => $log->id,
                    'changes' => array_intersect_key($changes, array_flip($significantFields)),
                    'original' => array_intersect_key($log->getOriginal(), array_flip($significantFields)),
                ],
                performedBy: Auth::id()
            );
        }
    }

    /**
     * Handle the AttendanceLog "deleted" event.
     */
    public function deleted(AttendanceLog $log): void
    {
        $this->markRelatedSummariesDirty($log);

        AuditLogger::attendance(
            action: 'deleted',
            workerId: $log->worker_id,
            data: [
                'log_id' => $log->id,
                'type' => $log->type,
                'device_time' => $log->device_time?->toIso8601String(),
            ],
            performedBy: Auth::id()
        );
    }

    /**
     * Mark all work summaries that include this log's device_time as dirty.
     * This ensures weekly, monthly, and yearly summaries get recalculated.
     */
    private function markRelatedSummariesDirty(AttendanceLog $log): void
    {
        if (!$log->device_time || !$log->worker_id) {
            return;
        }

        WorkSummary::where('worker_id', $log->worker_id)
            ->where('period_start', '<=', $log->device_time)
            ->where('period_end', '>=', $log->device_time)
            ->update(['is_dirty' => true]);
    }
}
