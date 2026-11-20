<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The advance notice sent to the current verified address when an
 * administrator cancels a pending email change (§14.5).
 *
 * The message confirms that the current verified address is unchanged and
 * deliberately omits internal administrative details.
 */
class EmailChangeCancelledMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message.
     */
    public function __construct(
        public readonly string $currentEmail,
    ) {}

    /**
     * Build the text-only message.
     *
     * @return $this
     */
    public function build(): static
    {
        return $this
            ->subject('Your TestOps email change was cancelled')
            ->text('mail.email-change.cancelled');
    }
}
