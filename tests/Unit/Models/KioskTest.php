<?php

namespace Tests\Unit\Models;

use App\Models\Kiosk;
use PHPUnit\Framework\TestCase;

class KioskTest extends TestCase
{
    public function test_generate_secret_token_returns_64_character_string(): void
    {
        $token = Kiosk::generateSecretToken();

        $this->assertEquals(64, strlen($token));
    }

    public function test_generate_secret_token_is_random(): void
    {
        $token1 = Kiosk::generateSecretToken();
        $token2 = Kiosk::generateSecretToken();

        $this->assertNotEquals($token1, $token2);
    }

    public function test_is_active_returns_true_for_active_status(): void
    {
        $kiosk = new Kiosk();
        $kiosk->status = 'active';

        $this->assertTrue($kiosk->isActive());
    }

    public function test_is_active_returns_false_for_inactive_status(): void
    {
        $kiosk = new Kiosk();
        $kiosk->status = 'inactive';

        $this->assertFalse($kiosk->isActive());
    }

    public function test_is_active_returns_false_for_maintenance_status(): void
    {
        $kiosk = new Kiosk();
        $kiosk->status = 'maintenance';

        $this->assertFalse($kiosk->isActive());
    }

    public function test_hidden_attributes_are_configured(): void
    {
        $kiosk = new Kiosk();

        $hidden = $kiosk->getHidden();

        $this->assertContains('secret_token', $hidden);
    }

    public function test_fillable_attributes_are_configured(): void
    {
        $kiosk = new Kiosk();

        $fillable = $kiosk->getFillable();

        $this->assertContains('name', $fillable);
        $this->assertContains('code', $fillable);
        $this->assertContains('secret_token', $fillable);
        $this->assertContains('location', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('latitude', $fillable);
        $this->assertContains('longitude', $fillable);
        $this->assertContains('last_heartbeat_at', $fillable);
    }

    public function test_casts_are_configured(): void
    {
        $kiosk = new Kiosk();

        $casts = $kiosk->getCasts();

        $this->assertEquals('decimal:8', $casts['latitude']);
        $this->assertEquals('decimal:8', $casts['longitude']);
        $this->assertEquals('datetime', $casts['last_heartbeat_at']);
    }
}
