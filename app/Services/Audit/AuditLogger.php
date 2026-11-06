<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogger
{
    /**
     * Keys that must never be persisted in audit snapshots.
     *
     * @var array<int, string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'token_hash',
        'access_token',
        'refresh_token',
        'verification_token',
        'reset_token',
        'secret',
        'client_secret',
        'authorization',
        'remember_token',
        'credential',
        'credentials',
    ];

    /**
     * Record an audit event with identity snapshots and safe business values.
     *
     * @param  array<string, mixed>|null  $beforeValues
     * @param  array<string, mixed>|null  $afterValues
     */
    public function log(
        ?User $actor,
        ?User $target,
        string $action,
        ?array $beforeValues = null,
        ?array $afterValues = null,
        ?string $reason = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'actor_user_id' => $actor?->getKey(),
            'actor_name' => $actor?->name,
            'actor_email' => $actor?->email,
            'target_user_id' => $target?->getKey(),
            'target_name' => $target?->name,
            'target_email' => $target?->email,
            'action' => $action,
            'before_values' => $this->sanitize($beforeValues),
            'after_values' => $this->sanitize($afterValues),
            'reason' => $reason,
        ]);
    }

    /**
     * Remove sensitive fields recursively from audit snapshot values.
     *
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $safeValues = [];

        foreach ($values as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                continue;
            }

            if (is_array($value)) {
                $value = $this->sanitize($value);
            }

            $safeValues[$key] = $value;
        }

        return $safeValues;
    }
}
