<?php

namespace Database\Seeders;

use App\Models\AttendanceLog;
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
                'name' => 'Ahmet Yilmaz',
                'phone' => '+905550000002',
                'employee_id' => 'REP001',
                'password' => 'password123',
                'role' => 'representative',
                'status' => 'active',
                'secret_token' => User::generateSecretToken(),
            ]
        );

        $this->command->info('Creating ' . self::WORKER_COUNT . ' workers...');

        // Create workers in batches
        $workerData = [];

        for ($i = 1; $i <= self::WORKER_COUNT; $i++) {
            $workerData[] = [
                'name' => $this->generateName(),
                'email' => "worker{$i}@example.com",
                'phone' => '+90556' . str_pad($i, 7, '0', STR_PAD_LEFT),
                'employee_id' => 'WRK' . str_pad($i, 5, '0', STR_PAD_LEFT),
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
            'Mehmet', 'Ahmet', 'Ali', 'Mustafa', 'Hasan', 'Huseyin', 'Ibrahim', 'Osman', 'Yusuf', 'Emre',
            'Fatma', 'Ayse', 'Emine', 'Hatice', 'Zeynep', 'Elif', 'Merve', 'Esra', 'Selin', 'Deniz',
            'Burak', 'Cem', 'Kaan', 'Eren', 'Baris', 'Umut', 'Tolga', 'Serkan', 'Onur', 'Mert',
            'Gamze', 'Gizem', 'Tugba', 'Ozlem', 'Sevgi', 'Derya', 'Melek', 'Asli', 'Ceren', 'Ebru',
        ];

        $lastNames = [
            'Yilmaz', 'Kaya', 'Demir', 'Celik', 'Sahin', 'Yildiz', 'Ozturk', 'Aydin', 'Ozdemir', 'Arslan',
            'Dogan', 'Kilic', 'Aslan', 'Cetin', 'Kara', 'Koc', 'Kurt', 'Ozkan', 'Simsek', 'Polat',
            'Erdogan', 'Gunes', 'Ak', 'Korkmaz', 'Caliskan', 'Kaplan', 'Bulut', 'Tekin', 'Aksoy', 'Yalcin',
        ];

        return $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
    }
}
