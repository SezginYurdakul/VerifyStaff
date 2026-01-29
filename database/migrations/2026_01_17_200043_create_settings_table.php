<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('group')->default('general'); // general, work_hours, shifts, attendance
            $table->text('value');
            $table->string('type')->default('string'); // string, integer, boolean, json, time
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index('group');
        });

        // Insert default settings
        $this->seedDefaultSettings();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }

    private function seedDefaultSettings(): void
    {
        $settings = [
            // Work Hours Settings
            [
                'key' => 'work_start_time',
                'group' => 'work_hours',
                'value' => '09:00',
                'type' => 'time',
                'description' => 'Standard work start time (HH:MM)',
            ],
            [
                'key' => 'work_end_time',
                'group' => 'work_hours',
                'value' => '18:00',
                'type' => 'time',
                'description' => 'Standard work end time (HH:MM)',
            ],
            [
                'key' => 'break_duration_minutes',
                'group' => 'work_hours',
                'value' => '60',
                'type' => 'integer',
                'description' => 'Lunch/break duration in minutes',
            ],
            [
                'key' => 'regular_work_minutes',
                'group' => 'work_hours',
                'value' => '480',
                'type' => 'integer',
                'description' => 'Regular work duration per day in minutes (8 hours = 480)',
            ],

            // Attendance Rules
            [
                'key' => 'late_threshold_minutes',
                'group' => 'attendance',
                'value' => '15',
                'type' => 'integer',
                'description' => 'Grace period for late arrival in minutes',
            ],
            [
                'key' => 'early_departure_threshold_minutes',
                'group' => 'attendance',
                'value' => '15',
                'type' => 'integer',
                'description' => 'Threshold for early departure in minutes before end time',
            ],
            [
                'key' => 'overtime_threshold_minutes',
                'group' => 'attendance',
                'value' => '30',
                'type' => 'integer',
                'description' => 'Minimum extra minutes to count as overtime',
            ],
            [
                'key' => 'duplicate_scan_window_minutes',
                'group' => 'attendance',
                'value' => '5',
                'type' => 'integer',
                'description' => 'Time window to detect duplicate scans in minutes',
            ],

            // Working Days
            [
                'key' => 'working_days',
                'group' => 'schedule',
                'value' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
                'type' => 'json',
                'description' => 'Days of the week considered as working days',
            ],
            [
                'key' => 'weekend_days',
                'group' => 'schedule',
                'value' => json_encode(['saturday', 'sunday']),
                'type' => 'json',
                'description' => 'Days of the week considered as weekend',
            ],

            // Shift Settings
            [
                'key' => 'shifts_enabled',
                'group' => 'shifts',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Enable multiple shift support',
            ],
            [
                'key' => 'shifts',
                'group' => 'shifts',
                'value' => json_encode([
                    [
                        'name' => 'Morning Shift',
                        'code' => 'morning',
                        'start_time' => '06:00',
                        'end_time' => '14:00',
                        'break_minutes' => 30,
                    ],
                    [
                        'name' => 'Day Shift',
                        'code' => 'day',
                        'start_time' => '09:00',
                        'end_time' => '18:00',
                        'break_minutes' => 60,
                    ],
                    [
                        'name' => 'Evening Shift',
                        'code' => 'evening',
                        'start_time' => '14:00',
                        'end_time' => '22:00',
                        'break_minutes' => 30,
                    ],
                    [
                        'name' => 'Night Shift',
                        'code' => 'night',
                        'start_time' => '22:00',
                        'end_time' => '06:00',
                        'break_minutes' => 30,
                    ],
                ]),
                'type' => 'json',
                'description' => 'Available shift definitions',
            ],
            [
                'key' => 'default_shift',
                'group' => 'shifts',
                'value' => 'day',
                'type' => 'string',
                'description' => 'Default shift code for new workers',
            ],

            // General Settings
            [
                'key' => 'worker_qr_refresh_seconds',
                'group' => 'general',
                'value' => '30',
                'type' => 'integer',
                'description' => 'How often the worker QR code refreshes (15-60 seconds)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'kiosk_qr_refresh_seconds',
                'group' => 'general',
                'value' => '30',
                'type' => 'integer',
                'description' => 'How often the kiosk QR code refreshes (15-60 seconds)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'timezone',
                'group' => 'general',
                'value' => 'Europe/Istanbul',
                'type' => 'string',
                'description' => 'Default timezone for the system',
            ],
            [
                'key' => 'date_format',
                'group' => 'general',
                'value' => 'Y-m-d',
                'type' => 'string',
                'description' => 'Date format for display',
            ],
            [
                'key' => 'time_format',
                'group' => 'general',
                'value' => 'H:i',
                'type' => 'string',
                'description' => 'Time format for display (H:i for 24h, h:i A for 12h)',
            ],
            [
                'key' => 'attendance_mode',
                'group' => 'general',
                'value' => 'representative',
                'type' => 'string',
                'description' => 'Attendance mode: representative (worker shows QR, rep scans) or kiosk (kiosk shows QR, worker scans)',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->insert(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
};
