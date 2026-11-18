<?php

namespace App\Models;

use Database\Factories\InvitationLinkRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One emailed invitation or password-setup link.
 *
 * Rows exist so resends can rotate credentials, rate limits roll over the
 * persisted history, and acceptance is single-use. The raw token is never
 * stored, only its hash; delivery_id links the immutable delivery record
 * without touching notification_deliveries itself.
 */
#[Fillable([
    'user_invitation_id',
    'recipient_email',
    'credential_generation',
    'method',
    'token_hash',
    'expires_at',
    'consumed_at',
    'requested_by',
    'ip_address',
    'delivery_id',
])]
#[Hidden(['token_hash'])]
class InvitationLinkRequest extends Model
{
    /** @use HasFactory<InvitationLinkRequestFactory> */
    use HasFactory;

    public const METHOD_INVITATION_LINK = 'invitation_link';

    public const METHOD_DIRECT_ACCOUNT_SETUP = 'direct_account_setup';

    /**
     * Get the invitation this link belongs to.
     */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(UserInvitation::class, 'user_invitation_id');
    }

    /**
     * Get the administrator who requested the link, when applicable.
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get the recorded email delivery for this link request.
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(NotificationDelivery::class, 'delivery_id');
    }

    /**
     * Whether the link has been consumed by a successful acceptance.
     */
    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    /**
     * Whether the link is past its expiry.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'credential_generation' => 'integer',
        ];
    }
}
