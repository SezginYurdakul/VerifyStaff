<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $settings = [
            // Toggle Mode (auto in/out detection)
            [
                'key' => 'toggle_mode_enabled',
                'group' => 'attendance',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable automatic in/out detection based on last log (toggle mode)',
            ],

            // Auto Checkout
            [
                'key' => 'auto_checkout_enabled',
                'group' => 'attendance',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Automatically checkout workers who forgot at end of day',
            ],
            [
                'key' => 'auto_checkout_time',
                'group' => 'attendance',
                'value' => '23:59',
                'type' => 'time',
                'description' => 'Time to run auto checkout (HH:MM)',
            ],
        ];

        foreach ($settings as $setting) {
            // Only insert if not exists
            $exists = DB::table('settings')->where('key', $setting['key'])->exists();
            if (!$exists) {
                DB::table('settings')->insert(array_merge($setting, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'toggle_mode_enabled',
            'auto_checkout_enabled',
            'auto_checkout_time',
        ])->delete();
    }
};
