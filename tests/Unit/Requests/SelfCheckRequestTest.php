<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\Api\SelfCheckRequest;
use PHPUnit\Framework\TestCase;

class SelfCheckRequestTest extends TestCase
{
    private SelfCheckRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new SelfCheckRequest();
    }

    public function test_authorize_returns_true(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_rules_has_device_time_field(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('device_time', $rules);
        $this->assertContains('required', $rules['device_time']);
        $this->assertContains('date', $rules['device_time']);
    }

    public function test_rules_has_device_timezone_field(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('device_timezone', $rules);
        $this->assertContains('sometimes', $rules['device_timezone']);
        $this->assertContains('string', $rules['device_timezone']);
        $this->assertContains('max:50', $rules['device_timezone']);
    }

    public function test_rules_has_kiosk_code_field(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('kiosk_code', $rules);
        $this->assertContains('required', $rules['kiosk_code']);
        $this->assertContains('string', $rules['kiosk_code']);
        $this->assertContains('max:20', $rules['kiosk_code']);
    }

    public function test_rules_has_kiosk_totp_field(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('kiosk_totp', $rules);
        $this->assertContains('required', $rules['kiosk_totp']);
        $this->assertContains('string', $rules['kiosk_totp']);
        $this->assertContains('size:6', $rules['kiosk_totp']);
    }

    public function test_rules_has_latitude_field(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('latitude', $rules);
        $this->assertContains('sometimes', $rules['latitude']);
        $this->assertContains('nullable', $rules['latitude']);
        $this->assertContains('numeric', $rules['latitude']);
        $this->assertContains('between:-90,90', $rules['latitude']);
    }

    public function test_rules_has_longitude_field(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('longitude', $rules);
        $this->assertContains('sometimes', $rules['longitude']);
        $this->assertContains('nullable', $rules['longitude']);
        $this->assertContains('numeric', $rules['longitude']);
        $this->assertContains('between:-180,180', $rules['longitude']);
    }

    public function test_has_custom_messages(): void
    {
        $messages = $this->request->messages();

        $this->assertArrayHasKey('device_time.required', $messages);
        $this->assertArrayHasKey('device_time.date', $messages);
        $this->assertArrayHasKey('kiosk_code.required', $messages);
        $this->assertArrayHasKey('kiosk_totp.required', $messages);
        $this->assertArrayHasKey('kiosk_totp.size', $messages);
    }
}
