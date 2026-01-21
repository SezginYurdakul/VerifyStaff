<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\Api\RegisterRequest;
use PHPUnit\Framework\TestCase;

class RegisterRequestTest extends TestCase
{
    private RegisterRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new RegisterRequest();
    }

    public function test_authorize_returns_true(): void
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_rules_has_name_field(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertContains('required', $rules['name']);
        $this->assertContains('string', $rules['name']);
        $this->assertContains('max:255', $rules['name']);
    }

    public function test_rules_has_email_field(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('email', $rules);
        $this->assertContains('nullable', $rules['email']);
        $this->assertContains('email', $rules['email']);
    }

    public function test_rules_has_phone_field(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('phone', $rules);
        $this->assertContains('nullable', $rules['phone']);
        $this->assertContains('string', $rules['phone']);
        $this->assertContains('max:20', $rules['phone']);
    }

    public function test_rules_has_employee_id_field(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('employee_id', $rules);
        $this->assertContains('nullable', $rules['employee_id']);
        $this->assertContains('string', $rules['employee_id']);
        $this->assertContains('max:50', $rules['employee_id']);
    }

    public function test_rules_has_password_field(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('password', $rules);
        $this->assertContains('required', $rules['password']);
        $this->assertContains('confirmed', $rules['password']);
    }

    public function test_rules_has_role_field(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('role', $rules);
        $this->assertContains('sometimes', $rules['role']);
        $this->assertContains('in:admin,representative,worker', $rules['role']);
    }

    public function test_email_has_unique_constraint(): void
    {
        $rules = $this->request->rules();

        $this->assertContains('unique:users,email', $rules['email']);
    }

    public function test_phone_has_unique_constraint(): void
    {
        $rules = $this->request->rules();

        $this->assertContains('unique:users,phone', $rules['phone']);
    }

    public function test_employee_id_has_unique_constraint(): void
    {
        $rules = $this->request->rules();

        $this->assertContains('unique:users,employee_id', $rules['employee_id']);
    }
}
