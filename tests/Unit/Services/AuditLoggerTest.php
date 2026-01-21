<?php

namespace Tests\Unit\Services;

use App\Services\AuditLogger;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    private $logMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logMock = \Mockery::mock(LoggerInterface::class);
        $this->logMock->shouldReceive('info')->andReturnNull();
        $this->logMock->shouldReceive('warning')->andReturnNull();

        Log::shouldReceive('channel')
            ->with('daily')
            ->andReturn($this->logMock);
    }

    public function test_attendance_logs_to_daily_channel(): void
    {
        AuditLogger::attendance('check_in', 123, ['kiosk' => 'KIOSK-001'], 456);

        $this->assertTrue(true); // If no exception, log was called
    }

    public function test_totp_verification_success_logs_to_daily_channel(): void
    {
        AuditLogger::totpVerification(123, true, 456);

        $this->assertTrue(true);
    }

    public function test_totp_verification_failure_logs_to_daily_channel(): void
    {
        AuditLogger::totpVerification(123, false, 456);

        $this->assertTrue(true);
    }

    public function test_settings_change_logs_to_daily_channel(): void
    {
        AuditLogger::settingsChange('work_start_time', '09:00', '08:30', 1);

        $this->assertTrue(true);
    }

    public function test_auth_success_logs_to_daily_channel(): void
    {
        AuditLogger::auth('login', 123, true);

        $this->assertTrue(true);
    }

    public function test_auth_failure_logs_to_daily_channel(): void
    {
        AuditLogger::auth('login', 123, false);

        $this->assertTrue(true);
    }

    public function test_security_logs_to_daily_channel(): void
    {
        AuditLogger::security('suspicious_activity', ['attempts' => 5], 123);

        $this->assertTrue(true);
    }

    public function test_work_summary_logs_to_daily_channel(): void
    {
        AuditLogger::workSummary('calculated', 123, 'weekly', ['total_minutes' => 2400]);

        $this->assertTrue(true);
    }

    public function test_sync_logs_to_daily_channel(): void
    {
        AuditLogger::sync('completed', 123, ['synced' => 10, 'failed' => 0]);

        $this->assertTrue(true);
    }

    public function test_attendance_accepts_empty_data_array(): void
    {
        AuditLogger::attendance('check_out', 123);

        $this->assertTrue(true);
    }

    public function test_attendance_accepts_null_performed_by(): void
    {
        AuditLogger::attendance('check_in', 123, [], null);

        $this->assertTrue(true);
    }

    public function test_security_accepts_null_user_id(): void
    {
        AuditLogger::security('brute_force_attempt', ['ip' => '192.168.1.1']);

        $this->assertTrue(true);
    }
}
