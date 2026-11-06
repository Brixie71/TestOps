<?php

namespace App\Models;

use Database\Factories\NotificationDeliveryRetryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One manual retry attempt of an original notification delivery.
 *
 * Attempts are immutable once finalized: the only permitted write is the
 * single pending-to-outcome finalization that records the result of the
 * transport attempt performed outside the reservation transaction.
 */
#[Fillable([
    'notification_delivery_id',
    'attempt_number',
    'status',
    'failure_code',
    'failure_message',
    'user_message',
    'attempted_at',
    'completed_at',
])]
class NotificationDeliveryRetry extends Model
{
    /** @use HasFactory<NotificationDeliveryRetryFactory> */
    use HasFactory;

    /**
     * Retry attempt outcomes.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    /**
     * The table stores attempt timestamps only.
     */
    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    /**
     * Prevent modification or deletion of finalized attempts and prevent
     * deletion of any attempt.
     */
    protected static function booted(): void
    {
        static::updating(function (self $retry): void {
            if ($retry->getOriginal('status') !== self::STATUS_PENDING) {
                throw new LogicException('Notification delivery retry records cannot be updated.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Notification delivery retry records cannot be deleted.');
        });
    }

    /**
     * Get the original delivery this attempt belongs to.
     */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(NotificationDelivery::class, 'notification_delivery_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
