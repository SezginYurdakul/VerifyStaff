<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\Api\LoginRequest;
use PHPUnit\Framework\TestCase;

class LoginRequestTest extends TestCase
{
    private LoginRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new LoginRequest();
    }

    public function test_authorize_returns_true(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_rules_has_identifier_field(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('identifier', $rules);
        $this->assertContains('required', $rules['identifier']);
        $this->assertContains('string', $rules['identifier']);
    }

    public function test_rules_has_password_field(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('password', $rules);
        $this->assertContains('required', $rules['password']);
        $this->assertContains('string', $rules['password']);
    }

    public function test_has_custom_messages(): void
    {
        $messages = $this->request->messages();

        $this->assertArrayHasKey('identifier.required', $messages);
    }
}
