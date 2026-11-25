<?php

namespace App\Models;

use Database\Factories\NotificationDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Original notification delivery record.
 *
 * Records are immutable: retry operations never rewrite them, and the
 * effective status is derived from the latest retry instead.
 *
 * User-facing boundary for future profile interfaces: notification_type,
 * created_at, effectiveStatus(), and user_message are safe to expose to the
 * authenticated recipient. failure_code and failure_message are internal
 * diagnostics, and retries() exposes retry controls/internals — neither may
 * be shown to ordinary users.
 */
#[Fillable([
    'user_id',
    'recipient_email',
    'notification_type',
    'status',
    'suppression_reason',
    'failure_code',
    'failure_message',
    'user_message',
    'context',
    'delivered_at',
])]
class NotificationDelivery extends Model
{
    /** @use HasFactory<NotificationDeliveryFactory> */
    use HasFactory;

    /**
     * Original delivery outcomes.
     */
    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    /**
     * Deliberately not sent because the recipient's preferences disable the
     * notification category (§14.4). Never a delivery failure.
     */
    public const STATUS_SUPPRESSED = 'suppressed';

    /**
     * The recipient disabled the optional category in their notification
     * preferences.
     */
    public const SUPPRESSION_REASON_PREFERENCE_DISABLED = 'preference_disabled';

    /**
     * The model does not use an updated_at column.
     */
    public const UPDATED_AT = null;

    /**
     * Prevent modification or deletion of historical delivery records.
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Notification delivery records cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Notification delivery records cannot be deleted.');
        });
    }

    /**
     * Get the user account the notification was sent to, when known.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the manual retry attempts for this delivery, oldest first.
     */
    public function retries(): HasMany
    {
        return $this->hasMany(NotificationDeliveryRetry::class)
            ->orderBy('attempt_number');
    }

    /**
     * Determine the effective delivery status from the latest retry without
     * changing the original status.
     */
    public function effectiveStatus(): string
    {
        $latestRetry = $this->retries()
            ->orderByDesc('attempt_number')
            ->first();

        return $latestRetry->status ?? $this->status;
    }

    /**
     * Get the user-facing label for a suppression reason, or null when the
     * delivery was not suppressed.
     */
    public function suppressionReasonLabel(): ?string
    {
        if ($this->suppression_reason === null) {
            return null;
        }

        return match ($this->suppression_reason) {
            self::SUPPRESSION_REASON_PREFERENCE_DISABLED => 'Disabled in your notification preferences',
            default => str_replace('_', ' ', ucfirst($this->suppression_reason)),
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
            'context' => 'array',
            'delivered_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
