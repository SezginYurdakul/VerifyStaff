<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\Api\VerifyTotpRequest;
use PHPUnit\Framework\TestCase;

class VerifyTotpRequestTest extends TestCase
{
    private VerifyTotpRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new VerifyTotpRequest();
    }

    public function test_rules_has_worker_id_field(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('worker_id', $rules);
        $this->assertContains('required', $rules['worker_id']);
        $this->assertContains('integer', $rules['worker_id']);
        $this->assertContains('exists:users,id', $rules['worker_id']);
    }

    public function test_rules_has_code_field(): void
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('code', $rules);
        $this->assertContains('required', $rules['code']);
        $this->assertContains('string', $rules['code']);
        $this->assertContains('size:6', $rules['code']);
    }

    public function test_has_custom_messages(): void
    {
        $messages = $this->request->messages();

        $this->assertArrayHasKey('worker_id.required', $messages);
        $this->assertArrayHasKey('worker_id.integer', $messages);
        $this->assertArrayHasKey('worker_id.exists', $messages);
        $this->assertArrayHasKey('code.required', $messages);
        $this->assertArrayHasKey('code.size', $messages);
    }

    public function test_code_must_be_exactly_6_characters(): void
    {
        $rules = $this->request->rules();

        $this->assertContains('size:6', $rules['code']);
    }
}
