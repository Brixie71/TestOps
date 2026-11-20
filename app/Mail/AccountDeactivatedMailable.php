<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The mandatory security notice sent when an account is deactivated (§14.2).
 *
 * The message deliberately omits the administrative reason and any internal
 * account details beyond the holder's own address.
 */
class AccountDeactivatedMailable extends Mailable
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
            ->subject('Your TestOps account has been deactivated')
            ->text('mail.account.deactivated');
    }
}
