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

        // Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@verifystaff.com'],
            [
                'name' => 'Admin User',
                'phone' => '+905550000001',
                'employee_id' => 'ADMIN001',
                'password' => 'password123',
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
                'password' => 'password123',
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

        for ($i = 1; $i <= self::WORKER_COUNT; $i++) {
            // Assign department in round-robin fashion
            $departmentId = $departmentIds[($i - 1) % count($departmentIds)];

            $workerData[] = [
                'name' => $this->generateName(),
                'email' => "worker{$i}@example.com",
                'phone' => '+316' . str_pad($i, 8, '0', STR_PAD_LEFT),
                'employee_id' => 'WRK' . str_pad($i, 5, '0', STR_PAD_LEFT),
                'department_id' => $departmentId,
                'password' => bcrypt('password123'),
                'role' => 'worker',
                'status' => 'active',
                'secret_token' => User::generateSecretToken(),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($workerData) >= 100) {
                DB::table('users')->insert($workerData);
                $workerData = [];
                $this->command->info("Created " . min($i, self::WORKER_COUNT) . " workers...");
            }
        }

        if (!empty($workerData)) {
            DB::table('users')->insert($workerData);
        }

        // Get all worker IDs
        $workerIds = User::where('role', 'worker')->pluck('id')->toArray();

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

        // We need to insert check-in first, get its ID, then insert check-out with paired_log_id
        // So we'll process in smaller batches per worker

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

                // Insert check-in log
                $checkInId = DB::table('attendance_logs')->insertGetId(
                    $this->buildCheckInData($workerId, $repId, $checkInTime, $timezone, $isLate)
                );
                $logsCreated++;
                $totalLogs++;

                // Check-out log with pairing
                if ($logsCreated < self::LOGS_PER_WORKER) {
                    // Calculate work duration
                    $workMinutes = $checkInTime->diffInMinutes($checkOutTime);

                    // Check for overtime
                    $isOvertime = $workMinutes > self::REGULAR_WORK_MINUTES;
                    $overtimeMinutes = $isOvertime ? $workMinutes - self::REGULAR_WORK_MINUTES : 0;

                    // Check for early departure
                    $expectedEnd = $checkOutTime->copy()->setTimeFromTimeString(self::WORK_END_TIME);
                    $isEarlyDeparture = $checkOutTime->lt($expectedEnd);

                    // Insert check-out log
                    $checkOutId = DB::table('attendance_logs')->insertGetId(
                        $this->buildCheckOutData($workerId, $repId, $checkOutTime, $timezone, $checkInId, $workMinutes, $isOvertime, $overtimeMinutes, $isEarlyDeparture)
                    );

                    // Update check-in with paired_log_id
                    DB::table('attendance_logs')->where('id', $checkInId)->update(['paired_log_id' => $checkOutId]);

                    $logsCreated++;
                    $totalLogs++;
                }
            }

            // Progress update per worker
            if (($workerIndex + 1) % 10 === 0) {
                $percentage = round(($totalLogs / $targetLogs) * 100, 1);
                $this->command->info("Progress: {$totalLogs} / {$targetLogs} logs ({$percentage}%)");
            }
        }

        $this->command->info("Progress: {$totalLogs} / {$targetLogs} logs (100%)");
    }

    private function buildCheckInData(int $workerId, int $repId, Carbon $deviceTime, string $timezone, bool $isLate): array
    {
        $eventId = hash('sha256', $workerId . $repId . $deviceTime->toIso8601String() . 'in' . uniqid());

        return [
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
            'paired_log_id' => null, // Will be updated after check-out is created
            'work_minutes' => null,
            'is_overtime' => null,
            'overtime_minutes' => null,
            'is_early_departure' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function buildCheckOutData(int $workerId, int $repId, Carbon $deviceTime, string $timezone, int $pairedLogId, int $workMinutes, bool $isOvertime, int $overtimeMinutes, bool $isEarlyDeparture): array
    {
        $eventId = hash('sha256', $workerId . $repId . $deviceTime->toIso8601String() . 'out' . uniqid());

        return [
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
            'created_at' => now(),
            'updated_at' => now(),
        ];
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
