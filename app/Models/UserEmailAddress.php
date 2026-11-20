<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'email',
    'status',
    'verified_at',
])]
class UserEmailAddress extends Model
{
    /**
     * The address the user currently signs in with.
     */
    public const STATUS_CURRENT = 'current';

    /**
     * The address reserved at credential-issue time, before activation.
     */
    public const STATUS_RESERVED = 'reserved';

    /**
     * A self-service email change awaiting verification; the current address
     * stays active until the pending one is verified (§9.4).
     */
    public const STATUS_PENDING = 'pending';

    /**
     * A previously verified address that was replaced by a newer one; it
     * remains reserved for this account (§9.2).
     */
    public const STATUS_PREVIOUS = 'previous';

    /**
     * Get the user who owns this email address.
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
            'verified_at' => 'datetime',
        ];
    }
}
