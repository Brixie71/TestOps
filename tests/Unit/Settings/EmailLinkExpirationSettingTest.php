<?php

namespace Tests\Unit\Settings;

use App\Models\SystemSetting;
use App\Services\Settings\EmailLinkExpirationSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EmailLinkExpirationSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_setting_returns_default_value(): void
    {
        $setting = new EmailLinkExpirationSetting;

        $this->assertSame(60, $setting->getMinutes());

        $this->assertDatabaseCount('system_settings', 0);
    }

    public function test_existing_setting_value_is_returned(): void
    {
        SystemSetting::query()->create([
            'setting_key' => 'email_link_expiration_minutes',
            'setting_value' => '120',
        ]);

        $setting = new EmailLinkExpirationSetting;

        $this->assertSame(120, $setting->getMinutes());
    }

    public function test_setting_can_be_updated_to_minimum_value(): void
    {
        $setting = new EmailLinkExpirationSetting;

        $this->assertSame(1, $setting->updateMinutes(1));

        $this->assertSame(1, $setting->getMinutes());

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'email_link_expiration_minutes',
            'setting_value' => '1',
        ]);
    }

    public function test_setting_can_be_updated_to_maximum_value(): void
    {
        $setting = new EmailLinkExpirationSetting;

        $this->assertSame(1440, $setting->updateMinutes(1440));

        $this->assertSame(1440, $setting->getMinutes());

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'email_link_expiration_minutes',
            'setting_value' => '1440',
        ]);
    }

    public function test_updating_existing_setting_does_not_create_duplicate_record(): void
    {
        SystemSetting::query()->create([
            'setting_key' => 'email_link_expiration_minutes',
            'setting_value' => '60',
        ]);

        $setting = new EmailLinkExpirationSetting;

        $setting->updateMinutes(120);

        $this->assertDatabaseCount('system_settings', 1);

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'email_link_expiration_minutes',
            'setting_value' => '120',
        ]);
    }

    public function test_zero_minutes_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        (new EmailLinkExpirationSetting)->updateMinutes(0);
    }

    public function test_negative_minutes_are_rejected(): void
    {
        $this->expectException(ValidationException::class);

        (new EmailLinkExpirationSetting)->updateMinutes(-1);
    }

    public function test_values_above_maximum_are_rejected(): void
    {
        $this->expectException(ValidationException::class);

        (new EmailLinkExpirationSetting)->updateMinutes(1441);
    }
}
