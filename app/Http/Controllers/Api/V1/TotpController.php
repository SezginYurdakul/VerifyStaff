<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\TotpVerified;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\VerifyTotpRequest;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TotpController extends Controller
{
    public function __construct(
        private TotpService $totpService
    ) {}

    public function generateCode(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isWorker()) {
            return response()->json([
                'message' => 'Only workers can generate TOTP codes.',
            ], 403);
        }

        if (!$user->secret_token) {
            return response()->json([
                'message' => 'No secret token assigned to this user.',
            ], 400);
        }

        $result = $this->totpService->generateCode($user->secret_token);

        return response()->json([
            'code' => $result['code'],
            'expires_at' => $result['expires_at'],
            'remaining_seconds' => $result['remaining_seconds'],
            'refresh_seconds' => $this->totpService->getTimeStep(),
            'qr_data' => $result['qr_data'],
        ]);
    }

    public function verifyCode(VerifyTotpRequest $request): JsonResponse
    {
        $worker = User::where('id', $request->validated('worker_id'))
            ->where('role', 'worker')
            ->where('status', 'active')
            ->first();

        if (!$worker) {
            return response()->json([
                'message' => 'Worker not found or inactive.',
                'valid' => false,
            ], 404);
        }

        if (!$worker->secret_token) {
            return response()->json([
                'message' => 'Worker has no secret token.',
                'valid' => false,
            ], 400);
        }

        $isValid = $this->totpService->verifyCode($worker->secret_token, $request->validated('code'));

        // Dispatch event for logging
        TotpVerified::dispatch($worker, $isValid, $request->user());

        return response()->json([
            'valid' => $isValid,
            'worker_id' => $worker->id,
            'worker_name' => $worker->name,
            'verified_at' => $isValid ? now()->toIso8601String() : null,
        ]);
    }
}
