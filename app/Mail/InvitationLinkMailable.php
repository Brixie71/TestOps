<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The initial invitation link with the account's recovery code.
 *
 * Raw tokens and recovery codes only ever travel inside this email; they
 * are never persisted, logged, or audited.
 */
class InvitationLinkMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message.
     */
    public function __construct(
        public readonly string $acceptUrl,
        public readonly ?string $recoveryCode = null,
        public readonly int $expiresInMinutes = 15,
    ) {}

    /**
     * Build the text-only message.
     *
     * @return $this
     */
    public function build(): static
    {
        return $this
            ->subject('Your TestOps invitation')
            ->text('mail.invitation.link');
    }
}
