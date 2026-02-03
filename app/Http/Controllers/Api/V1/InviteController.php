<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\InviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InviteController extends Controller
{
    public function __construct(
        private InviteService $inviteService
    ) {}

    /**
     * Validate an invite token.
     */
    public function validate(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $user = $this->inviteService->validateToken($request->token);

        if (!$user) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid or expired invitation link',
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Accept invitation and set password.
     */
    public function accept(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $this->inviteService->validateToken($request->token);

        if (!$user) {
            return response()->json([
                'message' => 'Invalid or expired invitation link',
            ], 422);
        }

        $this->inviteService->acceptInvite($user, $request->password);

        // Create auth token for automatic login
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Password set successfully',
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }
}
