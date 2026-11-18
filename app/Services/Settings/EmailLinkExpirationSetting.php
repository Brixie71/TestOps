<?php

namespace App\Services\Settings;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EmailLinkExpirationSetting
{
    private const SETTING_KEY = 'email_link_expiration_minutes';

    public const DEFAULT_MINUTES = 60;

    public const MIN_MINUTES = 1;

    public const MAX_MINUTES = 1440;

    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function getMinutes(): int
    {
        $value = SystemSetting::query()
            ->where('setting_key', self::SETTING_KEY)
            ->value('setting_value');

        if ($value === null) {
            return self::DEFAULT_MINUTES;
        }

        return (int) $value;
    }

    /**
     * Update the configured duration, auditing every changed value as
     * settings.changed with the acting administrator. An unchanged value
     * writes nothing and audits nothing.
     *
     * @return int The effective value after the update.
     *
     * @throws ValidationException
     */
    public function updateMinutes(int $minutes, ?User $actor = null): int
    {
        Validator::make(
            ['minutes' => $minutes],
            [
                'minutes' => [
                    'required',
                    'integer',
                    'min:'.self::MIN_MINUTES,
                    'max:'.self::MAX_MINUTES,
                ],
            ],
        )->validate();

        $currentMinutes = $this->getMinutes();

        if ((string) $currentMinutes === (string) $minutes) {
            return $currentMinutes;
        }

        SystemSetting::query()->updateOrCreate(
            ['setting_key' => self::SETTING_KEY],
            ['setting_value' => (string) $minutes],
        );

        $this->auditLogger->log(
            $actor,
            null,
            'settings.changed',
            ['setting_key' => self::SETTING_KEY, 'setting_value' => (string) $currentMinutes],
            ['setting_key' => self::SETTING_KEY, 'setting_value' => (string) $minutes],
        );

        return $minutes;
    }
}
