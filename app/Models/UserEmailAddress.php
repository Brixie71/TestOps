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
