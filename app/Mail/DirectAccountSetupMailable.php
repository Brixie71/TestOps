<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The direct account-setup link for accounts created directly by an
 * administrator.
 *
 * Raw tokens only ever travel inside this email; they are never persisted,
 * logged, or audited.
 */
class DirectAccountSetupMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message.
     */
    public function __construct(
        public readonly string $setupUrl,
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
            ->subject('Set up your TestOps account')
            ->text('mail.invitation.password-setup');
    }
}
