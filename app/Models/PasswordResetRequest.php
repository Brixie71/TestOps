<?php

namespace App\Models;

use Database\Factories\PasswordResetRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One forced password-reset request (§11.3).
 *
 * The lifecycle mirrors email verification requests: issued (usable),
 * consumed (completed successfully), expired, or invalidated (superseded or
 * disabled after repeated failed attempts). Only the token hash is stored;
 * the raw token travels once through the reset email.
 */
#[Fillable([
    'user_id',
    'token_hash',
    'state',
    'failed_attempts',
    'expires_at',
    'consumed_at',
    'expired_at',
    'invalidated_at',
])]
#[Hidden(['token_hash'])]
class PasswordResetRequest extends Model
{
    /** @use HasFactory<PasswordResetRequestFactory> */
    use HasFactory;

    public const STATE_ISSUED = 'issued';

    public const STATE_CONSUMED = 'consumed';

    public const STATE_EXPIRED = 'expired';

    public const STATE_INVALIDATED = 'invalidated';

    /**
     * The account this reset belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'failed_attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'expired_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }
}
