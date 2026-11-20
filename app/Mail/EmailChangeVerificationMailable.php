<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The single-use confirmation link for a self-service email change (§9.4).
 *
 * Raw tokens only ever travel inside this email; they are never persisted,
 * logged, or audited.
 */
class EmailChangeVerificationMailable extends Mailable
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
            ->subject('Confirm your new TestOps email address')
            ->text('mail.email-change.verify');
    }
}
