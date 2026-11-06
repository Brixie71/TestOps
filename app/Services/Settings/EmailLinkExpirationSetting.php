<?php

namespace App\Services\Settings;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EmailLinkExpirationSetting
{
    private const SETTING_KEY = 'email_link_expiration_minutes';

    public const DEFAULT_MINUTES = 60;

    public const MIN_MINUTES = 1;

    public const MAX_MINUTES = 1440;

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
     * @throws ValidationException
     */
    public function updateMinutes(int $minutes): int
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

        SystemSetting::query()->updateOrCreate(
            ['setting_key' => self::SETTING_KEY],
            ['setting_value' => (string) $minutes],
        );

        return $minutes;
    }
}
