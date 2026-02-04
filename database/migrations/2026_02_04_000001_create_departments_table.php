<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // e.g., 'WAREHOUSE', 'OFFICE'
            $table->time('shift_start')->default('09:00:00');
            $table->time('shift_end')->default('18:00:00');
            $table->integer('late_threshold_minutes')->default(15);
            $table->integer('early_departure_threshold_minutes')->default(15);
            $table->integer('regular_work_minutes')->default(480); // 8 hours
            $table->json('working_days')->nullable(); // ['monday', 'tuesday', ...] - null means use global setting
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
