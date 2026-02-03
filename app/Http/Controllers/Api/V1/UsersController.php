<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\InviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UsersController extends Controller
{
    public function __construct(
        private InviteService $inviteService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $perPage = $request->integer('per_page', 20);
        $role = $request->string('role');
        $status = $request->string('status');

        $query = User::query()->orderBy('created_at', 'desc');

        if ($role->isNotEmpty()) {
            $query->where('role', $role);
        }

        if ($status->isNotEmpty()) {
            $query->where('status', $status);
        }

        $users = $query->paginate($perPage);

        return response()->json([
            'users' => UserResource::collection($users),
            'total' => $users->total(),
            'per_page' => $users->perPage(),
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'employee_id' => ['nullable', 'string', 'max:50', 'unique:users,employee_id'],
            'role' => ['required', Rule::in(['admin', 'representative', 'worker'])],
        ]);

        $newUser = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'employee_id' => $validated['employee_id'] ?? null,
            'role' => $validated['role'],
            'status' => 'active',
            'secret_token' => User::generateSecretToken(),
            'password' => null, // User will set via invite
        ]);

        // Generate invite and send email
        $this->inviteService->createAndSendInvite($newUser);

        return response()->json([
            'message' => 'User created and invitation sent',
            'user' => new UserResource($newUser),
        ], 201);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $authUser = $request->user();

        if (!$authUser->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $authUser = $request->user();

        if (!$authUser->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'employee_id' => ['nullable', 'string', 'max:50', Rule::unique('users', 'employee_id')->ignore($user->id)],
            'role' => ['sometimes', Rule::in(['admin', 'representative', 'worker'])],
            'status' => ['sometimes', Rule::in(['active', 'inactive', 'suspended'])],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $authUser = $request->user();

        if (!$authUser->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Prevent self-deletion
        if ($authUser->id === $user->id) {
            return response()->json(['message' => 'Cannot delete your own account'], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    public function resendInvite(Request $request, User $user): JsonResponse
    {
        $authUser = $request->user();

        if (!$authUser->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($user->invite_accepted_at !== null) {
            return response()->json(['message' => 'User has already accepted the invitation'], 422);
        }

        $this->inviteService->createAndSendInvite($user);

        return response()->json([
            'message' => 'Invitation resent successfully',
        ]);
    }
}
