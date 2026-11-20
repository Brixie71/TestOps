<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Request-level failed-attempt counter for email verification (§10.5).
 *
 * One row exists per user and purpose. Unlike the token-level counter on the
 * verification request row, this counter survives token replacement and is
 * reset only by a successful verification. Reaching the approved maximum
 * arms a timed lockout during which no replacement token may be issued; the
 * pending email reservation is never released by this counter.
 */
#[Fillable([
    'user_id',
    'purpose',
    'failed_attempts',
    'locked_until',
])]
class EmailVerificationAttemptCounter extends Model
{
    /**
     * Get the user the counter belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether the counter is currently inside its lockout window.
     */
    public function isLocked(?\DateTimeInterface $now = null): bool
    {
        return $this->locked_until !== null
            && $this->locked_until->greaterThan($now ?? now());
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
            'locked_until' => 'datetime',
        ];
    }
}
