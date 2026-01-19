<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('attendance_logs', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 64)->unique();
            $table->foreignId('worker_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('rep_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', ['in', 'out']);
            $table->timestamp('device_time');
            $table->string('device_timezone', 50)->default('UTC');
            $table->timestamp('sync_time')->nullable();
            $table->unsignedInteger('sync_attempt')->default(1);
            $table->unsignedInteger('offline_duration_seconds')->default(0);
            $table->enum('sync_status', ['pending', 'syncing', 'synced', 'failed'])->default('synced');
            $table->boolean('flagged')->default(false);
            $table->string('flag_reason')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            // Paired check-out reference (for check-in records)
            $table->foreignId('paired_log_id')->nullable()->constrained('attendance_logs')->nullOnDelete();

            // Calculated fields (set on check-out)
            $table->unsignedInteger('work_minutes')->nullable();
            $table->boolean('is_late')->nullable();
            $table->boolean('is_early_departure')->nullable();
            $table->boolean('is_overtime')->nullable();
            $table->unsignedInteger('overtime_minutes')->nullable();

            $table->timestamps();

            $table->index(['worker_id', 'device_time']);
            $table->index(['rep_id', 'device_time']);
            $table->index('sync_status');
            $table->index(['worker_id', 'type', 'device_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_logs');
    }
};
