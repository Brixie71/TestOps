<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The forced password-reset link sent when an administrator requires an
 * account holder to reset their password.
 *
 * Raw tokens only ever travel inside this email; they are never persisted,
 * logged, or audited. The new password is chosen by the account holder and
 * is never emailed by anyone.
 */
class PasswordResetMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message.
     */
    public function __construct(
        public readonly string $resetUrl,
        public readonly int $expiresInMinutes,
    ) {}

    /**
     * Build the text-only message.
     *
     * @return $this
     */
    public function build(): static
    {
        return $this
            ->subject('Reset your TestOps password')
            ->text('mail.password-reset.forced');
    }
}
