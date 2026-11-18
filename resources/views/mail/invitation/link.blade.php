You were invited to TestOps.

Use the invitation link below within {{ $expiresInMinutes }} minute(s) to set up your account. The link works once:

    {{ $acceptUrl }}

@if ($recoveryCode)
Keep this recovery code with the email address you were invited with. If the invitation link expires or is lost, the code can recover a fresh link for the same address:

    Recovery code: {{ $recoveryCode }}
@endif

If you were not expecting this invitation, you can safely ignore this email.
