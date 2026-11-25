<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Optional-category project activity notification (A15.3): membership and
 * ownership changes inside one project.
 *
 * The message carries only the project name, the activity summary, and a
 * link to the project; no addresses, permissions, or internal identifiers.
 */
class ProjectActivityMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message.
     */
    public function __construct(
        public readonly string $activitySummary,
        public readonly string $projectName,
        public readonly string $projectUrl,
    ) {}

    /**
     * Build the text-only message.
     *
     * @return $this
     */
    public function build(): static
    {
        return $this
            ->subject('Project activity: '.$this->projectName)
            ->text('mail.project.activity');
    }
}
