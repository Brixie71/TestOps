<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Optional-category system announcement (§14.3): maintenance notices and
 * release announcements composed by a Super Admin.
 *
 * The message carries only the administrator-authored subject and plain-text
 * body; no internal account details are added.
 */
class SystemAnnouncementMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message.
     */
    public function __construct(
        public readonly string $announcementSubject,
        public readonly string $announcementBody,
    ) {}

    /**
     * Build the text-only message.
     *
     * @return $this
     */
    public function build(): static
    {
        return $this
            ->subject($this->announcementSubject)
            ->text('mail.announcement.system');
    }
}
