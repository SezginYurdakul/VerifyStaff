<?php

namespace Database\Seeders;

use App\Models\AttendanceLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Admin User
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@verifystaff.com',
            'phone' => '+905550000001',
            'employee_id' => 'ADMIN001',
            'password' => 'password123',
            'role' => 'admin',
            'status' => 'active',
            'secret_token' => User::generateSecretToken(),
        ]);

        // Representative User
        $rep = User::create([
            'name' => 'Ahmet Yilmaz',
            'email' => 'rep@verifystaff.com',
            'phone' => '+905550000002',
            'employee_id' => 'REP001',
            'password' => 'password123',
            'role' => 'representative',
            'status' => 'active',
            'secret_token' => User::generateSecretToken(),
        ]);

        // Workers
        $workers = [
            [
                'name' => 'Mehmet Demir',
                'email' => 'mehmet@example.com',
                'phone' => '+905551000001',
                'employee_id' => 'EMP001',
            ],
            [
                'name' => 'Ayse Kaya',
                'email' => 'ayse@example.com',
                'phone' => '+905551000002',
                'employee_id' => 'EMP002',
            ],
            [
                'name' => 'Fatma Celik',
                'email' => 'fatma@example.com',
                'phone' => '+905551000003',
                'employee_id' => 'EMP003',
            ],
            [
                'name' => 'Ali Ozturk',
                'email' => 'ali@example.com',
                'phone' => '+905551000004',
                'employee_id' => 'EMP004',
            ],
            [
                'name' => 'Zeynep Arslan',
                'email' => 'zeynep@example.com',
                'phone' => '+905551000005',
                'employee_id' => 'EMP005',
            ],
        ];

        $createdWorkers = [];
        foreach ($workers as $workerData) {
            $createdWorkers[] = User::create([
                ...$workerData,
                'password' => 'password123',
                'role' => 'worker',
                'status' => 'active',
                'secret_token' => User::generateSecretToken(),
            ]);
        }

        // Create attendance logs for the past 2 weeks
        $this->createAttendanceLogs($createdWorkers, $rep);
    }

    private function createAttendanceLogs(array $workers, User $rep): void
    {
        $timezone = 'Europe/Istanbul';
        $today = Carbon::today($timezone);

        // Generate logs for the past 14 days (excluding weekends)
        for ($dayOffset = 13; $dayOffset >= 0; $dayOffset--) {
            $date = $today->copy()->subDays($dayOffset);

            // Skip weekends
            if ($date->isWeekend()) {
                continue;
            }

            foreach ($workers as $worker) {
                // Random variations for realistic data
                $isAbsent = rand(1, 100) <= 5; // 5% chance of absence

                if ($isAbsent) {
                    continue;
                }

                // Check-in time: 08:30 - 09:15 (some late arrivals)
                $checkInHour = 8;
                $checkInMinute = rand(30, 59);
                $isLate = rand(1, 100) <= 20; // 20% chance of being late

                if ($isLate) {
                    $checkInHour = 9;
                    $checkInMinute = rand(0, 15);
                }

                $checkInTime = $date->copy()->setTime($checkInHour, $checkInMinute, rand(0, 59));

                // Check-out time: 17:30 - 18:30 (some overtime)
                $checkOutHour = rand(17, 18);
                $checkOutMinute = rand(0, 59);

                if ($checkOutHour === 17 && $checkOutMinute < 30) {
                    $checkOutMinute = rand(30, 59); // Ensure at least 17:30
                }

                $checkOutTime = $date->copy()->setTime($checkOutHour, $checkOutMinute, rand(0, 59));

                // Sometimes missing checkout (flagged)
                $missingCheckout = rand(1, 100) <= 3; // 3% chance

                // Create check-in log
                $this->createLog($worker, $rep, 'in', $checkInTime, $timezone, $isLate);

                // Create check-out log (unless missing)
                if (!$missingCheckout) {
                    $this->createLog($worker, $rep, 'out', $checkOutTime, $timezone, false);
                }
            }
        }
    }

    private function createLog(User $worker, User $rep, string $type, Carbon $deviceTime, string $timezone, bool $flagged = false): void
    {
        $eventId = AttendanceLog::generateEventId(
            $worker->id,
            $rep->id,
            $deviceTime->toIso8601String(),
            $type
        );

        AttendanceLog::create([
            'event_id' => $eventId,
            'worker_id' => $worker->id,
            'rep_id' => $rep->id,
            'type' => $type,
            'device_time' => $deviceTime,
            'device_timezone' => $timezone,
            'sync_time' => $deviceTime->copy()->addMinutes(rand(1, 60)),
            'sync_attempt' => 1,
            'offline_duration_seconds' => rand(0, 3600),
            'sync_status' => 'synced',
            'flagged' => $flagged,
            'flag_reason' => $flagged ? 'Late arrival' : null,
            'latitude' => 41.0082 + (rand(-100, 100) / 10000),
            'longitude' => 28.9784 + (rand(-100, 100) / 10000),
        ]);
    }
}
