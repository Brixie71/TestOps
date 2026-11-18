<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class AccountLinkExpirationSettingsSeeder extends Seeder
{
    /**
     * Seed the default values of the three account-link expiration
     * settings. Existing rows are preserved; the effective defaults stay in
     * App\Services\Settings\AccountLinkExpirationSettings.
     */
    public function run(): void
    {
        $defaults = [
            'accounts.invitation_link_expiration_minutes' => '15',
            'accounts.recovery_code_expiration_minutes' => '15',
            'accounts.email_verification_expiration_minutes' => '5',
        ];

        foreach ($defaults as $settingKey => $settingValue) {
            SystemSetting::firstOrCreate(
                ['setting_key' => $settingKey],
                ['setting_value' => $settingValue],
            );
        }
    }
}
