<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The mandatory security notice sent after a self-service password change
 * (§14.2).
 *
 * The message never contains the new password or any other credential.
 */
class PasswordChangedMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message.
     */
    public function __construct(
        public readonly string $accountEmail,
    ) {}

    /**
     * Build the text-only message.
     *
     * @return $this
     */
    public function build(): static
    {
        return $this
            ->subject('Your TestOps password has been changed')
            ->text('mail.account.password-changed');
    }
}
