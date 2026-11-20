<?php

namespace App\Models;

use Database\Factories\EmailVerificationRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A purpose-aware email verification request (account_activation or the
 * email_change purpose).
 *
 * The lifecycle states are issued, consumed, expired, and invalidated; the
 * raw token is never stored, only its hash. token_hash, token, and any
 * credential material must never be exposed to users or administrators.
 */
#[Fillable([
    'user_id',
    'purpose',
    'email',
    'token_hash',
    'state',
    'expires_at',
    'issued_at',
    'requested_ip',
    'failed_attempts',
    'consumed_at',
    'expired_at',
    'invalidated_at',
])]
#[Hidden(['token_hash'])]
class EmailVerificationRequest extends Model
{
    /** @use HasFactory<EmailVerificationRequestFactory> */
    use HasFactory;

    public const STATE_ISSUED = 'issued';

    public const STATE_CONSUMED = 'consumed';

    public const STATE_EXPIRED = 'expired';

    public const STATE_INVALIDATED = 'invalidated';

    public const PURPOSE_ACCOUNT_ACTIVATION = 'account_activation';

    public const PURPOSE_EMAIL_CHANGE = 'email_change';

    /**
     * Get the user the request belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether the request is outstanding and still consumable.
     */
    public function isOutstanding(): bool
    {
        return $this->state === self::STATE_ISSUED;
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
            'issued_at' => 'datetime',
            'failed_attempts' => 'integer',
            'consumed_at' => 'datetime',
            'expired_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }
}
