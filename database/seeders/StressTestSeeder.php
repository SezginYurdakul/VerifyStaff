<?php

namespace Database\Seeders;

use App\Models\AttendanceLog;
use App\Models\Department;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StressTestSeeder extends Seeder
{
    private const WORKER_COUNT = 200;
    private const LOGS_PER_WORKER = 500;
    private const BATCH_SIZE = 1000;

    // Work time constants (same as SyncController)
    private const REGULAR_WORK_MINUTES = 480; // 8 hours
    private const WORK_START_TIME = '09:00';
    private const WORK_END_TIME = '18:00';
    private const LATE_THRESHOLD_MINUTES = 15;

    public function run(): void
    {
        $this->command->info('Creating admin and representative...');
        $passwordHash = bcrypt('password123');

        // Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@verifystaff.com'],
            [
                'name' => 'Admin User',
                'phone' => '+905550000001',
                'employee_id' => 'ADMIN001',
                'password' => $passwordHash,
                'role' => 'admin',
                'status' => 'active',
                'secret_token' => User::generateSecretToken(),
            ]
        );

        // Representative User
        $rep = User::firstOrCreate(
            ['email' => 'rep@verifystaff.com'],
            [
                'name' => 'Willem de Jong',
                'phone' => '+31612345678',
                'employee_id' => 'REP001',
                'password' => $passwordHash,
                'role' => 'representative',
                'status' => 'active',
                'secret_token' => User::generateSecretToken(),
            ]
        );

        // Create departments if they don't exist
        $this->command->info('Creating departments...');
        $departments = $this->createDepartments();
        $departmentIds = array_column($departments, 'id');

        $this->command->info('Creating ' . self::WORKER_COUNT . ' workers...');

        // Create workers in batches
        $workerData = [];
        $targetWorkerEmails = [];

        for ($i = 1; $i <= self::WORKER_COUNT; $i++) {
            // Assign department in round-robin fashion
            $departmentId = $departmentIds[($i - 1) % count($departmentIds)];
            $email = "worker{$i}@example.com";
            $targetWorkerEmails[] = $email;

            $workerData[] = [
                'name' => $this->generateName(),
                'email' => $email,
                'phone' => '+316' . str_pad($i, 8, '0', STR_PAD_LEFT),
                'employee_id' => 'WRK' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'department_id' => $departmentId,
                'password' => $passwordHash,
                'role' => 'worker',
                'status' => 'active',
                'secret_token' => User::generateSecretToken(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($workerData) >= 100) {
                DB::table('users')->upsert(
                    $workerData,
                    ['email'],
                    ['name', 'phone', 'employee_id', 'department_id', 'password', 'status', 'updated_at']
                );
                $workerData = [];
                $this->command->info("Created " . min($i, self::WORKER_COUNT) . " workers...");
            }
        }

        if (!empty($workerData)) {
            DB::table('users')->upsert(
                $workerData,
                ['email'],
                ['name', 'phone', 'employee_id', 'department_id', 'password', 'status', 'updated_at']
            );
        }

        // Get only stress-test worker IDs
        $workerIds = User::where('role', 'worker')
            ->whereIn('email', $targetWorkerEmails)
            ->pluck('id')
            ->toArray();

        $this->command->info('Creating attendance logs (' . (self::WORKER_COUNT * self::LOGS_PER_WORKER) . ' total)...');

        $this->createAttendanceLogs($workerIds, $rep->id);

        $this->command->info('Stress test seeding completed!');
        $this->command->info('Total workers: ' . count($workerIds));
        $this->command->info('Total logs: ' . AttendanceLog::count());
    }

    private function createAttendanceLogs(array $workerIds, int $repId): void
    {
        $timezone = 'Europe/Istanbul';
        $totalLogs = 0;
        $targetLogs = count($workerIds) * self::LOGS_PER_WORKER;
        $nextLogId = ((int) DB::table('attendance_logs')->max('id')) + 1;
        $batchRows = [];
        $pairUpdates = [];
        $now = now();

        // Pre-assign IDs and insert in batches to avoid per-row insertGetId/update queries.

        foreach ($workerIds as $workerIndex => $workerId) {
            $dayOffset = 0;
            $logsCreated = 0;

            while ($logsCreated < self::LOGS_PER_WORKER) {
                // Go back in time
                $date = Carbon::today($timezone)->subDays($dayOffset);
                $dayOffset++;

                // Skip weekends
                if ($date->isWeekend()) {
                    continue;
                }

                // Random check-in time: 07:00 - 09:30
                $checkInHour = rand(7, 9);
                $checkInMinute = rand(0, 59);
                if ($checkInHour === 9 && $checkInMinute > 30) {
                    $checkInMinute = rand(0, 30);
                }

                $checkInTime = $date->copy()->setTime($checkInHour, $checkInMinute, rand(0, 59));

                // Random check-out time: 16:30 - 19:00
                $checkOutHour = rand(16, 19);
                $checkOutMinute = rand(0, 59);
                if ($checkOutHour === 16 && $checkOutMinute < 30) {
                    $checkOutMinute = rand(30, 59);
                }
                if ($checkOutHour === 19) {
                    $checkOutMinute = 0;
                }

                $checkOutTime = $date->copy()->setTime($checkOutHour, $checkOutMinute, rand(0, 59));

                // Calculate is_late for check-in
                $expectedStart = $checkInTime->copy()->setTimeFromTimeString(self::WORK_START_TIME);
                $graceEnd = $expectedStart->copy()->addMinutes(self::LATE_THRESHOLD_MINUTES);
                $isLate = $checkInTime->gt($graceEnd);

                $checkInId = $nextLogId++;
                $checkOutId = null;
                if ($logsCreated + 1 < self::LOGS_PER_WORKER) {
                    $checkOutId = $nextLogId++;
                }

                $batchRows[] = $this->buildCheckInData(
                    $checkInId,
                    $workerId,
                    $repId,
                    $checkInTime,
                    $timezone,
                    $isLate,
                    $now
                );
                if ($checkOutId !== null) {
                    $pairUpdates[$checkInId] = $checkOutId;
                }
                $logsCreated++;
                $totalLogs++;

                // Check-out log with pairing
                if ($checkOutId !== null) {
                    // Calculate work duration
                    $workMinutes = $checkInTime->diffInMinutes($checkOutTime);

                    // Check for overtime
                    $isOvertime = $workMinutes > self::REGULAR_WORK_MINUTES;
                    $overtimeMinutes = $isOvertime ? $workMinutes - self::REGULAR_WORK_MINUTES : 0;

                    // Check for early departure
                    $expectedEnd = $checkOutTime->copy()->setTimeFromTimeString(self::WORK_END_TIME);
                    $isEarlyDeparture = $checkOutTime->lt($expectedEnd);

                    $batchRows[] = $this->buildCheckOutData(
                        $checkOutId,
                        $workerId,
                        $repId,
                        $checkOutTime,
                        $timezone,
                        $checkInId,
                        $workMinutes,
                        $isOvertime,
                        $overtimeMinutes,
                        $isEarlyDeparture,
                        $now
                    );

                    $logsCreated++;
                    $totalLogs++;
                }

                if (count($batchRows) >= self::BATCH_SIZE) {
                    $this->flushAttendanceBatch($batchRows, $pairUpdates);
                }
            }

            // Progress update per worker
            if (($workerIndex + 1) % 10 === 0) {
                $percentage = round(($totalLogs / $targetLogs) * 100, 1);
                $this->command->info("Progress: {$totalLogs} / {$targetLogs} logs ({$percentage}%)");
            }
        }

        if (! empty($batchRows)) {
            $this->flushAttendanceBatch($batchRows, $pairUpdates);
        }

        $this->syncAttendanceLogSequence();
        $this->command->info("Progress: {$totalLogs} / {$targetLogs} logs (100%)");
    }

    private function buildCheckInData(
        int $id,
        int $workerId,
        int $repId,
        Carbon $deviceTime,
        string $timezone,
        bool $isLate,
        Carbon $now
    ): array
    {
        $eventId = "seed-{$workerId}-{$id}-in";

        return [
            'id' => $id,
            'event_id' => $eventId,
            'worker_id' => $workerId,
            'rep_id' => $repId,
            'type' => 'in',
            'device_time' => $deviceTime,
            'device_timezone' => $timezone,
            'sync_time' => $deviceTime->copy()->addMinutes(rand(1, 120)),
            'sync_attempt' => 1,
            'offline_duration_seconds' => rand(0, 7200),
            'sync_status' => 'synced',
            'flagged' => false,
            'flag_reason' => null,
            'latitude' => 41.0082 + (rand(-1000, 1000) / 100000),
            'longitude' => 28.9784 + (rand(-1000, 1000) / 100000),
            // Calculated fields for check-in
            'is_late' => $isLate,
            'paired_log_id' => null,
            'work_minutes' => null,
            'is_overtime' => null,
            'overtime_minutes' => null,
            'is_early_departure' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function flushAttendanceBatch(array &$batchRows, array &$pairUpdates): void
    {
        if (empty($batchRows)) {
            return;
        }

        DB::table('attendance_logs')->insert($batchRows);

        if (!empty($pairUpdates)) {
            $ids = array_keys($pairUpdates);
            $caseSql = 'CASE id';

            foreach ($pairUpdates as $checkInId => $checkOutId) {
                $caseSql .= " WHEN {$checkInId} THEN {$checkOutId}";
            }

            $caseSql .= ' END';

            DB::table('attendance_logs')
                ->whereIn('id', $ids)
                ->update(['paired_log_id' => DB::raw($caseSql)]);
        }

        $batchRows = [];
        $pairUpdates = [];
    }

    private function buildCheckOutData(
        int $id,
        int $workerId,
        int $repId,
        Carbon $deviceTime,
        string $timezone,
        int $pairedLogId,
        int $workMinutes,
        bool $isOvertime,
        int $overtimeMinutes,
        bool $isEarlyDeparture,
        Carbon $now
    ): array
    {
        $eventId = "seed-{$workerId}-{$id}-out";

        return [
            'id' => $id,
            'event_id' => $eventId,
            'worker_id' => $workerId,
            'rep_id' => $repId,
            'type' => 'out',
            'device_time' => $deviceTime,
            'device_timezone' => $timezone,
            'sync_time' => $deviceTime->copy()->addMinutes(rand(1, 120)),
            'sync_attempt' => 1,
            'offline_duration_seconds' => rand(0, 7200),
            'sync_status' => 'synced',
            'flagged' => false,
            'flag_reason' => null,
            'latitude' => 41.0082 + (rand(-1000, 1000) / 100000),
            'longitude' => 28.9784 + (rand(-1000, 1000) / 100000),
            // Calculated fields for check-out
            'is_late' => null,
            'paired_log_id' => $pairedLogId,
            'work_minutes' => $workMinutes,
            'is_overtime' => $isOvertime,
            'overtime_minutes' => $overtimeMinutes,
            'is_early_departure' => $isEarlyDeparture,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function syncAttendanceLogSequence(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("SELECT setval(pg_get_serial_sequence('attendance_logs', 'id'), COALESCE(MAX(id), 1), true) FROM attendance_logs");
    }

    private function generateName(): string
    {
        $firstNames = [
            'Jan', 'Pieter', 'Willem', 'Hendrik', 'Cornelis', 'Johannes', 'Gerrit', 'Jacobus', 'Dirk', 'Adriaan',
            'Anna', 'Maria', 'Elisabeth', 'Johanna', 'Cornelia', 'Wilhelmina', 'Margaretha', 'Geertruida', 'Helena', 'Catharina',
            'Bas', 'Daan', 'Sem', 'Lucas', 'Levi', 'Finn', 'Jesse', 'Milan', 'Luuk', 'Thijs',
            'Emma', 'Sophie', 'Julia', 'Lotte', 'Eva', 'Sanne', 'Lisa', 'Fleur', 'Isa', 'Noa',
        ];

        $lastNames = [
            'de Jong', 'Jansen', 'de Vries', 'van den Berg', 'van Dijk', 'Bakker', 'Janssen', 'Visser', 'Smit', 'Meijer',
            'de Boer', 'Mulder', 'de Groot', 'Bos', 'Vos', 'Peters', 'Hendriks', 'van Leeuwen', 'Dekker', 'Brouwer',
            'de Wit', 'Dijkstra', 'Smits', 'de Graaf', 'van der Meer', 'van der Linden', 'Kok', 'Jacobs', 'de Haan', 'Vermeulen',
        ];

        return $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
    }

    private function createDepartments(): array
    {
        $departmentsData = [
            [
                'name' => 'Warehouse',
                'code' => 'WH',
                'shift_start' => '07:00:00',
                'shift_end' => '16:00:00',
                'late_threshold_minutes' => 10,
                'early_departure_threshold_minutes' => 10,
                'regular_work_minutes' => 480,
                'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
                'description' => 'Warehouse operations - early shift',
                'is_active' => true,
            ],
            [
                'name' => 'Office',
                'code' => 'OFF',
                'shift_start' => '09:00:00',
                'shift_end' => '18:00:00',
                'late_threshold_minutes' => 15,
                'early_departure_threshold_minutes' => 15,
                'regular_work_minutes' => 480,
                'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'description' => 'Office staff - standard hours',
                'is_active' => true,
            ],
            [
                'name' => 'Security',
                'code' => 'SEC',
                'shift_start' => '22:00:00',
                'shift_end' => '06:00:00',
                'late_threshold_minutes' => 5,
                'early_departure_threshold_minutes' => 5,
                'regular_work_minutes' => 480,
                'working_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
                'description' => 'Security staff - night shift',
                'is_active' => true,
            ],
        ];

        $departments = [];
        foreach ($departmentsData as $data) {
            $department = Department::firstOrCreate(
                ['code' => $data['code']],
                $data
            );
            $departments[] = ['id' => $department->id, 'code' => $department->code];
        }

        $this->command->info('Created/Found ' . count($departments) . ' departments');

        return $departments;
    }
}
