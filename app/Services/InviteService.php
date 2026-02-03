<?php

namespace App\Services;

use App\Mail\UserInviteMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class InviteService
{
    /**
     * Create an invite token for the user and send invitation email.
     */
    public function createAndSendInvite(User $user): void
    {
        $token = User::generateInviteToken();
        $expiresAt = now()->addDays(7);

        $user->update([
            'invite_token' => $token,
            'invite_expires_at' => $expiresAt,
            'invite_accepted_at' => null,
        ]);

        // Refresh to get updated timestamps
        $user->refresh();

        Mail::to($user->email)->send(new UserInviteMail($user, $token));
    }

    /**
     * Validate an invite token and return the user if valid.
     */
    public function validateToken(string $token): ?User
    {
        $user = User::where('invite_token', $token)->first();

        if (!$user) {
            return null;
        }

        if (!$user->hasValidInvite()) {
            return null;
        }

        return $user;
    }

    /**
     * Accept the invite and set the user's password.
     */
    public function acceptInvite(User $user, string $password): void
    {
        $user->update([
            'password' => $password,
            'invite_accepted_at' => now(),
            'invite_token' => null,
            'invite_expires_at' => null,
        ]);
    }
}
