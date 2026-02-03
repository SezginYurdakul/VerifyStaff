<x-mail::message>
# Welcome to VerifyStaff

Hello {{ $userName }},

You've been invited to join VerifyStaff. Please click the button below to set your password and activate your account.

<x-mail::button :url="$inviteUrl">
Set Your Password
</x-mail::button>

This invitation link will expire on **{{ $expiresAt }}**.

If you didn't expect this invitation, you can safely ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
