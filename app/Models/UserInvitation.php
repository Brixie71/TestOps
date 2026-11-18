<?php

namespace App\Models;

use Database\Factories\UserInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One account offer for an invited email address.
 *
 * token_hash and recovery_code_hash are internal credential hashes and
 * must never be exposed to users or administrators. The live lifecycle is
 * derived: pending until accepted or cancelled, expiring by its own
 * expires_at, and cancelled through revoked_at with a recorded reason.
 */
#[Hidden(['token_hash', 'recovery_code_hash'])]

#[Fillable([
    'user_id',
    'invited_email',
    'token_hash',
    'expires_at',
    'accepted_at',
    'revoked_at',
    'created_by',
    'method',
    'acceptance_flow',
    'credential_generation',
    'recovery_code_hash',
    'recovery_code_expires_at',
    'recovery_attempts',
    'recovery_locked_until',
    'sent_count',
    'last_sent_at',
    'cancelled_reason',
    'cancelled_by',
    'expired_notified_at',
])]

class UserInvitation extends Model
{
    /** @use HasFactory<UserInvitationFactory> */
    use HasFactory;

    public const METHOD_INVITATION_LINK = 'invitation_link';

    public const METHOD_DIRECT_ACCOUNT_SETUP = 'direct_account_setup';

    public const ACCEPTANCE_FLOW_PASSWORD_SETUP_ONLY = 'password_setup_only';

    public const ACCEPTANCE_FLOW_REVIEW_THEN_PASSWORD = 'review_then_password';

    /**
     * Get the invited user account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the administrator who created the invitation.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the administrator who cancelled the invitation.
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * Get every emailed link generated for this invitation.
     */
    public function linkRequests(): HasMany
    {
        return $this->hasMany(InvitationLinkRequest::class, 'user_invitation_id');
    }

    /**
     * Whether the invitation was cancelled (revoked).
     */
    public function isCancelled(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Whether the invitation was accepted.
     */
    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    /**
     * Whether the invitation credentials are past their expiry.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * The derived live state: pending, expired, cancelled, or accepted.
     */
    public function state(): string
    {
        return match (true) {
            $this->isCancelled() => 'cancelled',
            $this->isAccepted() => 'accepted',
            default => $this->isExpired() ? 'expired' : 'pending',
        };
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
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'recovery_code_expires_at' => 'datetime',
            'recovery_locked_until' => 'datetime',
            'last_sent_at' => 'datetime',
            'expired_notified_at' => 'datetime',
            'credential_generation' => 'integer',
            'recovery_attempts' => 'integer',
            'sent_count' => 'integer',
        ];
    }
}
