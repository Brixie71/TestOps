<?php

namespace App\Services\Settings;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * The three account-link expiration settings.
 *
 * Each key has its own default and validation bounds:
 *
 * - accounts.invitation_link_expiration_minutes: invitation and
 *   password-setup links.
 * - accounts.recovery_code_expiration_minutes: invitation recovery codes.
 * - accounts.email_verification_expiration_minutes: account
 *   email-verification links.
 *
 * The existing email_link_expiration_minutes setting is intentionally not
 * covered here; its 60-minute default and 1-1440 bounds remain unchanged.
 *
 * Every changed value is audited as settings.changed with the acting
 * administrator and before/after values; unchanged values write nothing and
 * audit nothing. No credentials ever enter audit records.
 */
class AccountLinkExpirationSettings
{
    public const KEY_INVITATION_LINK = 'accounts.invitation_link_expiration_minutes';

    public const KEY_RECOVERY_CODE = 'accounts.recovery_code_expiration_minutes';

    public const KEY_EMAIL_VERIFICATION = 'accounts.email_verification_expiration_minutes';

    private const SETTINGS = [
        self::KEY_INVITATION_LINK => [
            'default' => 15,
            'min' => 1,
            'max' => 60,
        ],
        self::KEY_RECOVERY_CODE => [
            'default' => 15,
            'min' => 1,
            'max' => 30,
        ],
        self::KEY_EMAIL_VERIFICATION => [
            'default' => 5,
            'min' => 1,
            'max' => 60,
        ],
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Return every configured default.
     *
     * @return array{invitation_link_minutes:int, recovery_code_minutes:int, email_verification_minutes:int}
     */
    public function defaults(): array
    {
        return [
            'invitation_link_minutes' => self::SETTINGS[self::KEY_INVITATION_LINK]['default'],
            'recovery_code_minutes' => self::SETTINGS[self::KEY_RECOVERY_CODE]['default'],
            'email_verification_minutes' => self::SETTINGS[self::KEY_EMAIL_VERIFICATION]['default'],
        ];
    }

    /**
     * Get the effective value for one setting key.
     */
    public function getMinutes(string $settingKey): int
    {
        $this->assertKnownSettingKey($settingKey);

        $value = SystemSetting::query()
            ->where('setting_key', $settingKey)
            ->value('setting_value');

        if ($value === null) {
            return self::SETTINGS[$settingKey]['default'];
        }

        return (int) $value;
    }

    /**
     * Update one setting key with its bounds, auditing the change.
     *
     * An unchanged value writes nothing and audits nothing.
     *
     * @return int The effective value after the update.
     *
     * @throws ValidationException
     */
    public function update(string $settingKey, int $minutes, ?User $actor = null): int
    {
        $this->assertKnownSettingKey($settingKey);

        Validator::make(
            ['minutes' => $minutes],
            [
                'minutes' => [
                    'required',
                    'integer',
                    'min:'.self::SETTINGS[$settingKey]['min'],
                    'max:'.self::SETTINGS[$settingKey]['max'],
                ],
            ],
        )->validate();

        $currentMinutes = $this->getMinutes($settingKey);

        if ((string) $currentMinutes === (string) $minutes) {
            return $currentMinutes;
        }

        SystemSetting::query()->updateOrCreate(
            ['setting_key' => $settingKey],
            ['setting_value' => (string) $minutes],
        );

        $this->auditLogger->log(
            $actor,
            null,
            'settings.changed',
            ['setting_key' => $settingKey, 'setting_value' => (string) $currentMinutes],
            ['setting_key' => $settingKey, 'setting_value' => (string) $minutes],
        );

        return $minutes;
    }

    /**
     * Return the effective value of every account-link expiration setting.
     *
     * @return array<string, int>
     */
    public function all(): array
    {
        return [
            self::KEY_INVITATION_LINK => $this->getMinutes(self::KEY_INVITATION_LINK),
            self::KEY_RECOVERY_CODE => $this->getMinutes(self::KEY_RECOVERY_CODE),
            self::KEY_EMAIL_VERIFICATION => $this->getMinutes(self::KEY_EMAIL_VERIFICATION),
        ];
    }

    private function assertKnownSettingKey(string $settingKey): void
    {
        if (! isset(self::SETTINGS[$settingKey])) {
            throw new InvalidArgumentException(
                "Unsupported account-link expiration setting key [{$settingKey}].",
            );
        }
    }
}
