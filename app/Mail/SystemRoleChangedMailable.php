<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The mandatory security notice sent when a system role changes (§14.2).
 *
 * Null role names represent the absence of a system role (assignment or
 * removal).
 */
class SystemRoleChangedMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message.
     */
    public function __construct(
        public readonly ?string $previousRoleName,
        public readonly ?string $newRoleName,
    ) {}

    /**
     * Build the text-only message.
     *
     * @return $this
     */
    public function build(): static
    {
        return $this
            ->subject('Your TestOps system role has changed')
            ->text('mail.account.system-role-changed');
    }
}
