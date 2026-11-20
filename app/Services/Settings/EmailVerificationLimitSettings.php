<?php

namespace App\Services\Settings;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The Super Admin-configurable email-verification rate limits (§10.7).
 *
 * Only the per-IP hourly issuance limit is configurable; the per-account
 * limit is fixed by the approved specification. Changes are audited as
 * settings.changed, and unchanged values write and audit nothing.
 */
class EmailVerificationLimitSettings
{
    public const KEY_IP_HOURLY_LIMIT = 'accounts.email_verification_ip_hourly_limit';

    private const DEFAULT_IP_HOURLY_LIMIT = 10;

    private const MIN_IP_HOURLY_LIMIT = 1;

    private const MAX_IP_HOURLY_LIMIT = 100;

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Return the configured default for the per-IP hourly limit.
     */
    public function defaultIpHourlyLimit(): int
    {
        return self::DEFAULT_IP_HOURLY_LIMIT;
    }

    /**
     * Get the effective per-IP hourly issuance limit.
     */
    public function getIpHourlyLimit(): int
    {
        $value = SystemSetting::query()
            ->where('setting_key', self::KEY_IP_HOURLY_LIMIT)
            ->value('setting_value');

        return $value === null ? self::DEFAULT_IP_HOURLY_LIMIT : (int) $value;
    }

    /**
     * Update the per-IP hourly limit, auditing the change.
     *
     * @return int The effective value after the update.
     *
     * @throws ValidationException
     */
    public function updateIpHourlyLimit(int $limit, ?User $actor = null): int
    {
        Validator::make(
            ['limit' => $limit],
            [
                'limit' => [
                    'required',
                    'integer',
                    'min:'.self::MIN_IP_HOURLY_LIMIT,
                    'max:'.self::MAX_IP_HOURLY_LIMIT,
                ],
            ],
        )->validate();

        $currentLimit = $this->getIpHourlyLimit();

        if ($currentLimit === $limit) {
            return $currentLimit;
        }

        SystemSetting::query()->updateOrCreate(
            ['setting_key' => self::KEY_IP_HOURLY_LIMIT],
            ['setting_value' => (string) $limit],
        );

        $this->auditLogger->log(
            $actor,
            null,
            'settings.changed',
            ['setting_key' => self::KEY_IP_HOURLY_LIMIT, 'setting_value' => (string) $currentLimit],
            ['setting_key' => self::KEY_IP_HOURLY_LIMIT, 'setting_value' => (string) $limit],
        );

        return $limit;
    }
}
