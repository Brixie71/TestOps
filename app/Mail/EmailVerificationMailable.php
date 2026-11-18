<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The account email-verification link sent after password setup.
 *
 * Raw tokens only ever travel inside this email; they are never persisted,
 * logged, or audited.
 */
class EmailVerificationMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message.
     */
    public function __construct(
        public readonly string $verificationUrl,
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
            ->subject('Verify your TestOps email address')
            ->text('mail.verification.verify');
    }
}
