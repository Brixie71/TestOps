<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'actor_user_id',
    'actor_name',
    'actor_email',
    'target_user_id',
    'target_name',
    'target_email',
    'action',
    'before_values',
    'after_values',
    'reason',
])]
class AuditLog extends Model
{
    /**
     * The model does not use an updated_at column.
     */
    public const UPDATED_AT = null;

    /**
     * Prevent modification or deletion of existing audit records.
     */
    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('Audit log records cannot be updated.');
        });

        static::deleting(function (): void {
            throw new \LogicException('Audit log records cannot be deleted.');
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before_values' => 'array',
            'after_values' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
