<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AcceptInviteRequest;
use App\Http\Requests\Api\ValidateInviteRequest;
use App\Http\Resources\UserResource;
use App\Services\InviteService;
use Illuminate\Http\JsonResponse;

class InviteController extends Controller
{
    public function __construct(
        private InviteService $inviteService
    ) {}

    /**
     * Validate an invite token.
     */
    public function validate(ValidateInviteRequest $request): JsonResponse
    {
        $user = $this->inviteService->validateToken($request->validated('token'));

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
    public function accept(AcceptInviteRequest $request): JsonResponse
    {
        $user = $this->inviteService->validateToken($request->validated('token'));

        if (!$user) {
            return response()->json([
                'message' => 'Invalid or expired invitation link',
            ], 422);
        }

        $this->inviteService->acceptInvite($user, $request->validated('password'));

        // Create auth token for automatic login
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Password set successfully',
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }
}
