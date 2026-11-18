<?php

namespace App\Http\Controllers;

use App\Http\Resources\SystemSettingAccountLinkExpirationResource;
use App\Services\Settings\AccountLinkExpirationSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SystemSettingAccountLinkExpirationController extends Controller
{
    /**
     * Input names for the three account-link expiration settings, mapped to
     * their setting keys.
     */
    private const INPUT_KEYS = [
        'invitation_link_expiration_minutes' => AccountLinkExpirationSettings::KEY_INVITATION_LINK,
        'recovery_code_expiration_minutes' => AccountLinkExpirationSettings::KEY_RECOVERY_CODE,
        'email_verification_expiration_minutes' => AccountLinkExpirationSettings::KEY_EMAIL_VERIFICATION,
    ];

    /**
     * Show the configured account-link expiration durations.
     */
    public function show(AccountLinkExpirationSettings $settings): SystemSettingAccountLinkExpirationResource
    {
        return new SystemSettingAccountLinkExpirationResource(
            $this->minutesByInputName($settings->all()),
        );
    }

    /**
     * Update any of the three configured durations, independently.
     *
     * Machine clients are always denied at the authorization middleware;
     * writes remain reserved for the Super Admin role. Only provided input
     * names are updated, and unchanged values write and audit nothing.
     */
    public function update(
        Request $request,
        AccountLinkExpirationSettings $settings
    ): SystemSettingAccountLinkExpirationResource {
        $actor = $request->user() ?? Auth::guard('api')->user();

        foreach (self::INPUT_KEYS as $inputName => $settingKey) {
            if (! $request->has($inputName)) {
                continue;
            }

            $settings->update($settingKey, (int) $request->input($inputName), $actor);
        }

        // When no input name is provided the response reflects the current
        // state without any mutation.

        return new SystemSettingAccountLinkExpirationResource(
            $this->minutesByInputName($settings->all()),
        );
    }

    /**
     * Convert the setting-key keyed values into input-name keyed values.
     *
     * @param  array<string, int>  $minutesBySettingKey
     * @return array<string, int>
     */
    private function minutesByInputName(array $minutesBySettingKey): array
    {
        return [
            'invitation_link_minutes' => $minutesBySettingKey[AccountLinkExpirationSettings::KEY_INVITATION_LINK],
            'recovery_code_minutes' => $minutesBySettingKey[AccountLinkExpirationSettings::KEY_RECOVERY_CODE],
            'email_verification_minutes' => $minutesBySettingKey[AccountLinkExpirationSettings::KEY_EMAIL_VERIFICATION],
        ];
    }
}
