<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreKioskRequest;
use App\Http\Requests\Api\UpdateKioskRequest;
use App\Http\Resources\KioskResource;
use App\Models\Kiosk;
use App\Models\Setting;
use App\Services\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KioskController extends Controller
{
    public function __construct(
        private TotpService $totpService
    ) {}

    /**
     * Generate TOTP code for kiosk display.
     * Kiosk calls this endpoint to get a code to display in QR format.
     */
    public function generateCode(Request $request, string $kioskCode): JsonResponse
    {
        // Check if kiosk mode is enabled
        if (!Setting::isKioskMode()) {
            return response()->json([
                'message' => 'System is not in kiosk mode.',
                'attendance_mode' => 'representative',
            ], 403);
        }

        $kiosk = Kiosk::where('code', $kioskCode)->first();

        if (!$kiosk) {
            return response()->json([
                'message' => 'Kiosk not found.',
            ], 404);
        }

        if (!$kiosk->isActive()) {
            return response()->json([
                'message' => 'Kiosk is not active.',
                'status' => $kiosk->status,
            ], 403);
        }

        // Update heartbeat
        $kiosk->heartbeat();

        // Generate TOTP code using kiosk's secret token with kiosk-specific time step
        $result = $this->totpService->generateCodeForKiosk($kiosk->secret_token);

        // Create QR data that worker's app will scan
        $qrData = json_encode([
            'type' => 'kiosk_checkin',
            'kiosk_code' => $kiosk->code,
            'totp_code' => $result['code'],
            'timestamp' => time(),
            'api_endpoint' => '/api/v1/attendance/self-check',
        ]);

        return response()->json([
            'kiosk_code' => $kiosk->code,
            'kiosk_name' => $kiosk->name,
            'totp_code' => $result['code'],
            'expires_at' => $result['expires_at'],
            'remaining_seconds' => $result['remaining_seconds'],
            'refresh_seconds' => $this->totpService->getKioskTimeStep(),
            'qr_data' => base64_encode($qrData),
        ]);
    }

    /**
     * Verify a kiosk TOTP code.
     * Used internally by AttendanceController.
     */
    public function verifyKioskCode(string $kioskCode, string $totpCode): ?Kiosk
    {
        $kiosk = Kiosk::where('code', $kioskCode)
            ->where('status', 'active')
            ->first();

        if (!$kiosk) {
            return null;
        }

        $isValid = $this->totpService->verifyCode($kiosk->secret_token, $totpCode);

        return $isValid ? $kiosk : null;
    }

    /**
     * List all kiosks (Admin only).
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $kiosks = Kiosk::orderBy('code')->get();

        return response()->json([
            'kiosks' => KioskResource::collection($kiosks),
            'total' => $kiosks->count(),
        ]);
    }

    /**
     * Create a new kiosk (Admin only).
     */
    public function store(StoreKioskRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $kiosk = Kiosk::create([
            'name' => $validated['name'],
            'code' => Kiosk::generateCode(),
            'secret_token' => Kiosk::generateSecretToken(),
            'location' => $validated['location'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Kiosk created successfully',
            'kiosk' => new KioskResource($kiosk),
        ], 201);
    }

    /**
     * Get kiosk details (Admin only).
     */
    public function show(Request $request, string $kioskCode): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $kiosk = Kiosk::where('code', $kioskCode)->first();

        if (!$kiosk) {
            return response()->json(['message' => 'Kiosk not found.'], 404);
        }

        return response()->json([
            'kiosk' => new KioskResource($kiosk),
        ]);
    }

    /**
     * Update kiosk (Admin only).
     */
    public function update(UpdateKioskRequest $request, string $kioskCode): JsonResponse
    {
        $kiosk = Kiosk::where('code', $kioskCode)->first();

        if (!$kiosk) {
            return response()->json(['message' => 'Kiosk not found.'], 404);
        }

        $kiosk->update($request->validated());

        return response()->json([
            'message' => 'Kiosk updated successfully',
            'kiosk' => new KioskResource($kiosk->fresh()),
        ]);
    }

    /**
     * Regenerate kiosk secret token (Admin only).
     */
    public function regenerateToken(Request $request, string $kioskCode): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized. Admin access required.'], 403);
        }

        $kiosk = Kiosk::where('code', $kioskCode)->first();

        if (!$kiosk) {
            return response()->json(['message' => 'Kiosk not found.'], 404);
        }

        $kiosk->update([
            'secret_token' => Kiosk::generateSecretToken(),
        ]);

        return response()->json([
            'message' => 'Kiosk token regenerated successfully. Kiosk will need to be reconfigured.',
            'kiosk_code' => $kiosk->code,
        ]);
    }
}
