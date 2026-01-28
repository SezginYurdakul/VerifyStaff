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
        Schema::create('work_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_id')->constrained('users')->onDelete('cascade');
            $table->enum('period_type', ['daily', 'weekly', 'monthly', 'yearly']);
            $table->date('period_start');
            $table->date('period_end');

            // Calculated fields
            $table->unsignedInteger('total_minutes')->default(0);
            $table->unsignedInteger('regular_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->unsignedInteger('days_worked')->default(0);
            $table->unsignedInteger('days_absent')->default(0);
            $table->unsignedInteger('late_arrivals')->default(0);
            $table->unsignedInteger('early_departures')->default(0);

            // For anomaly tracking
            $table->unsignedInteger('missing_checkouts')->default(0);
            $table->unsignedInteger('missing_checkins')->default(0);

            // Smart recalculation tracking
            $table->boolean('is_dirty')->default(false);
            $table->string('source_hash', 32)->nullable(); // xxh3 hash for yearly validation

            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            // Unique constraint: one summary per worker per period
            $table->unique(['worker_id', 'period_type', 'period_start']);
            $table->index(['worker_id', 'period_type']);
            $table->index('period_start');
            $table->index('is_dirty'); // For efficient dirty summary queries
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_summaries');
    }
};
