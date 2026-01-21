<?php

namespace App\Services;

use PragmaRX\Google2FA\Google2FA;

class TotpService
{
    private Google2FA $google2fa;
    private int $window = 1; // Accept codes from -1 to +1 time windows (90 seconds total)

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    public function generateCode(string $secretToken): array
    {
        $secret = $this->deriveSecret($secretToken);
        $code = $this->google2fa->getCurrentOtp($secret);

        $timestamp = $this->google2fa->getTimestamp();
        $timeStep = 30;
        $currentWindow = floor($timestamp / $timeStep);
        $nextWindowStart = ($currentWindow + 1) * $timeStep;
        $remainingSeconds = $nextWindowStart - $timestamp;

        $qrData = json_encode([
            'user_id' => null,
            'code' => $code,
            'timestamp' => time(),
        ]);

        return [
            'code' => $code,
            'expires_at' => now()->addSeconds($remainingSeconds)->toIso8601String(),
            'remaining_seconds' => (int) $remainingSeconds,
            'qr_data' => base64_encode($qrData),
        ];
    }

    public function verifyCode(string $secretToken, string $code): bool
    {
        $secret = $this->deriveSecret($secretToken);

        return $this->google2fa->verifyKey($secret, $code, $this->window);
    }

    public function generateQrContent(int $userId, string $code): string
    {
        $data = [
            'user_id' => $userId,
            'code' => $code,
            'timestamp' => time(),
        ];

        return base64_encode(json_encode($data));
    }

    public function parseQrContent(string $qrContent): ?array
    {
        $decoded = base64_decode($qrContent);
        if ($decoded === false) {
            return null;
        }

        $data = json_decode($decoded, true);
        if (!$data || !isset($data['user_id'], $data['code'], $data['timestamp'])) {
            return null;
        }

        return $data;
    }

    private function deriveSecret(string $secretToken): string
    {
        // Create a deterministic base32 secret from the token
        // Google2FA requires a base32-encoded secret (A-Z, 2-7)
        $hash = hash('sha256', $secretToken, true);
        $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';

        // Use 16 bytes of hash to create a 16-character base32 secret
        for ($i = 0; $i < 16; $i++) {
            $byte = ord($hash[$i]);
            $secret .= $base32Chars[$byte % 32];
        }

        return $secret;
    }
}
