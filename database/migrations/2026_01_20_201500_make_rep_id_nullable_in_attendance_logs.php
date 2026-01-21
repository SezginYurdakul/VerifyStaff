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
        Schema::table('attendance_logs', function (Blueprint $table) {
            // Drop the foreign key first
            $table->dropForeign(['rep_id']);

            // Make rep_id nullable (for kiosk mode where there's no representative)
            $table->foreignId('rep_id')->nullable()->change();

            // Re-add the foreign key with nullable
            $table->foreign('rep_id')->references('id')->on('users')->nullOnDelete();

            // Add kiosk_id for kiosk mode attendance logs
            $table->string('kiosk_id', 20)->nullable()->after('longitude');
            $table->index('kiosk_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_logs', function (Blueprint $table) {
            $table->dropIndex(['kiosk_id']);
            $table->dropColumn('kiosk_id');

            $table->dropForeign(['rep_id']);
            $table->foreignId('rep_id')->nullable(false)->change();
            $table->foreign('rep_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
};
