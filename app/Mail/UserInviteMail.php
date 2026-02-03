<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInviteMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public User $user,
        public string $token
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You\'ve been invited to VerifyStaff',
        );
    }

    public function content(): Content
    {
        $inviteUrl = config('app.frontend_url', 'http://localhost:5173') . '/set-password?token=' . $this->token;
        $expiresAt = $this->user->invite_expires_at?->format('F j, Y') ?? now()->addDays(7)->format('F j, Y');

        return new Content(
            markdown: 'emails.user-invite',
            with: [
                'inviteUrl' => $inviteUrl,
                'userName' => $this->user->name,
                'expiresAt' => $expiresAt,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
