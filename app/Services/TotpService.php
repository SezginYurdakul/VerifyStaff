<?php

namespace App\Services;

use App\Models\Setting;
use PragmaRX\Google2FA\Google2FA;

class TotpService
{
    private Google2FA $google2fa;
    private int $window = 1; // Accept codes from -1 to +1 time windows
    private int $timeStep;

    public function __construct(int $timeStep = 30)
    {

        // Ensure valid range (15-60 seconds)
        $this->timeStep = max(15, min(60, $timeStep));

        $this->google2fa = new Google2FA();
        $this->google2fa->setKeyRegeneration($this->timeStep);
    }

    /**
     * Get the current time step in seconds.
     */
    public function getTimeStep(): int
    {
        return $this->timeStep;
    }

    /**
     * Get the kiosk time step in seconds from settings.
     */
    public function getKioskTimeStep(): int
    {
        try {
            $kioskTimeStep = (int) Setting::getValue('kiosk_qr_refresh_seconds', 30);
        } catch (\Exception $e) {
            $kioskTimeStep = 30; // Fallback
        }
        return max(15, min(60, $kioskTimeStep));
    }

    /**
     * Generate TOTP code for kiosk with kiosk-specific time step.
     */
    public function generateCodeForKiosk(string $secretToken): array
    {
        $kioskTimeStep = $this->getKioskTimeStep();

        // Create a temporary Google2FA instance with kiosk time step
        $kioskGoogle2fa = new Google2FA();
        $kioskGoogle2fa->setKeyRegeneration($kioskTimeStep);

        $secret = $this->deriveSecret($secretToken);
        $code = $kioskGoogle2fa->getCurrentOtp($secret);

        // getTimestamp() returns Unix timestamp divided by timeStep
        $normalizedTimestamp = $kioskGoogle2fa->getTimestamp();
        $currentWindow = floor($normalizedTimestamp);
        $nextWindowStart = $currentWindow + 1;
        // Calculate remaining seconds: (nextWindow - currentNormalizedTime) * timeStep
        $remainingSeconds = ($nextWindowStart - $normalizedTimestamp) * $kioskTimeStep;

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

    public function generateCode(string $secretToken): array
    {
        $secret = $this->deriveSecret($secretToken);
        $code = $this->google2fa->getCurrentOtp($secret);

        // getTimestamp() returns Unix timestamp divided by timeStep
        $normalizedTimestamp = $this->google2fa->getTimestamp();
        $currentWindow = floor($normalizedTimestamp);
        $nextWindowStart = $currentWindow + 1;
        // Calculate remaining seconds: (nextWindow - currentNormalizedTime) * timeStep
        $remainingSeconds = ($nextWindowStart - $normalizedTimestamp) * $this->timeStep;

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

    /**
     * Verify a TOTP code against a specific timestamp (for offline sync validation).
     * Uses a wider window to account for network/clock differences.
     *
     * @param string $secretToken The kiosk's secret token
     * @param string $code        The TOTP code captured at scan time
     * @param int    $unixTimestamp The device_time as Unix timestamp
     * @param int|null $timeStep  Override time step (e.g. kiosk time step). Defaults to instance timeStep.
     */
    public function verifyCodeAtTime(string $secretToken, string $code, int $unixTimestamp, ?int $timeStep = null): bool
    {
        $step = $timeStep ?? $this->timeStep;
        $secret = $this->deriveSecret($secretToken);

        // Create a Google2FA instance with the correct time step
        $g2fa = new Google2FA();
        $g2fa->setKeyRegeneration($step);

        // Convert unix timestamp to TOTP counter
        $timestamp = (int) floor($unixTimestamp / $step);

        // Use window=2 for offline verification (±2 steps)
        return (bool) $g2fa->verifyKey($secret, $code, 2, $timestamp);
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
