<?php

namespace Tests\Unit\Services;

use App\Services\TotpService;
use PHPUnit\Framework\TestCase;

class TotpServiceTest extends TestCase
{
    private TotpService $totpService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->totpService = new TotpService(30);
    }

    public function test_generate_code_returns_six_digit_code(): void
    {
        $secretToken = 'test-secret-token-12345';
        $result = $this->totpService->generateCode($secretToken);

        $this->assertArrayHasKey('code', $result);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertArrayHasKey('remaining_seconds', $result);
        $this->assertArrayHasKey('qr_data', $result);

        $this->assertEquals(6, strlen($result['code']));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $result['code']);
    }

    public function test_generate_code_returns_valid_remaining_seconds(): void
    {
        $secretToken = 'test-secret-token-12345';
        $result = $this->totpService->generateCode($secretToken);

        $this->assertGreaterThan(0, $result['remaining_seconds']);
        $this->assertLessThanOrEqual(30, $result['remaining_seconds']);
    }

    public function test_same_token_generates_same_code_within_time_window(): void
    {
        $secretToken = 'consistent-secret-token';

        $result1 = $this->totpService->generateCode($secretToken);
        $result2 = $this->totpService->generateCode($secretToken);

        $this->assertEquals($result1['code'], $result2['code']);
    }

    public function test_different_tokens_generate_different_codes(): void
    {
        $token1 = 'secret-token-one';
        $token2 = 'secret-token-two';

        $result1 = $this->totpService->generateCode($token1);
        $result2 = $this->totpService->generateCode($token2);

        $this->assertNotEquals($result1['code'], $result2['code']);
    }

    public function test_verify_code_returns_true_for_valid_code(): void
    {
        $secretToken = 'verify-test-token';

        $generated = $this->totpService->generateCode($secretToken);
        $isValid = $this->totpService->verifyCode($secretToken, $generated['code']);

        $this->assertTrue($isValid);
    }

    public function test_verify_code_returns_false_for_invalid_code(): void
    {
        $secretToken = 'verify-test-token';
        $invalidCode = '000000';

        $isValid = $this->totpService->verifyCode($secretToken, $invalidCode);

        $this->assertFalse($isValid);
    }

    public function test_verify_code_returns_false_for_wrong_token(): void
    {
        $secretToken1 = 'original-token';
        $secretToken2 = 'different-token';

        $generated = $this->totpService->generateCode($secretToken1);
        $isValid = $this->totpService->verifyCode($secretToken2, $generated['code']);

        $this->assertFalse($isValid);
    }

    public function test_generate_qr_content_returns_base64_encoded_json(): void
    {
        $userId = 123;
        $code = '456789';

        $qrContent = $this->totpService->generateQrContent($userId, $code);

        $decoded = base64_decode($qrContent);
        $this->assertNotFalse($decoded);

        $data = json_decode($decoded, true);
        $this->assertIsArray($data);
        $this->assertEquals($userId, $data['user_id']);
        $this->assertEquals($code, $data['code']);
        $this->assertArrayHasKey('timestamp', $data);
    }

    public function test_parse_qr_content_returns_array_for_valid_content(): void
    {
        $userId = 456;
        $code = '123456';

        $qrContent = $this->totpService->generateQrContent($userId, $code);
        $parsed = $this->totpService->parseQrContent($qrContent);

        $this->assertIsArray($parsed);
        $this->assertEquals($userId, $parsed['user_id']);
        $this->assertEquals($code, $parsed['code']);
        $this->assertArrayHasKey('timestamp', $parsed);
    }

    public function test_parse_qr_content_returns_null_for_invalid_base64(): void
    {
        $invalidContent = 'not-valid-base64!!!';

        $parsed = $this->totpService->parseQrContent($invalidContent);

        $this->assertNull($parsed);
    }

    public function test_parse_qr_content_returns_null_for_invalid_json(): void
    {
        $invalidJson = base64_encode('not valid json');

        $parsed = $this->totpService->parseQrContent($invalidJson);

        $this->assertNull($parsed);
    }

    public function test_parse_qr_content_returns_null_for_missing_fields(): void
    {
        $incompleteData = base64_encode(json_encode(['user_id' => 1]));

        $parsed = $this->totpService->parseQrContent($incompleteData);

        $this->assertNull($parsed);
    }

    public function test_code_generation_is_deterministic(): void
    {
        $secretToken = 'deterministic-test-token';

        // Generate code multiple times
        $codes = [];
        for ($i = 0; $i < 5; $i++) {
            $result = $this->totpService->generateCode($secretToken);
            $codes[] = $result['code'];
        }

        // All codes should be the same within the same time window
        $this->assertEquals(1, count(array_unique($codes)));
    }

    public function test_verify_accepts_codes_within_window(): void
    {
        $secretToken = 'window-test-token';

        // Generate and immediately verify
        $generated = $this->totpService->generateCode($secretToken);

        // Should accept the code (window = 1 means ±1 time period)
        $this->assertTrue($this->totpService->verifyCode($secretToken, $generated['code']));
    }

    public function test_generate_code_for_kiosk_returns_correct_structure(): void
    {
        $secretToken = 'kiosk-test-token';
        $result = $this->totpService->generateCodeForKiosk($secretToken);

        $this->assertArrayHasKey('code', $result);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertArrayHasKey('remaining_seconds', $result);
        $this->assertArrayHasKey('qr_data', $result);
        
        // Verify QR data decodes correctly
        $decodedQr = json_decode(base64_decode($result['qr_data']), true);
        $this->assertEquals($result['code'], $decodedQr['code']);
    }

    public function test_kiosk_time_step_falls_within_valid_range(): void
    {
        // Verify that the kiosk time step is between 15 and 60 seconds
        $kioskStep = $this->totpService->getKioskTimeStep();
        
        $this->assertGreaterThanOrEqual(15, $kioskStep);
        $this->assertLessThanOrEqual(60, $kioskStep);
    }
}
