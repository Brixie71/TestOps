<?php

namespace Tests\Unit\Settings;

use App\Models\AuditLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\AccountLinkExpirationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AccountLinkExpirationSettingsTest extends TestCase
{
    use RefreshDatabase;

    private AccountLinkExpirationSettings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = new AccountLinkExpirationSettings(new AuditLogger);
    }

    public function test_missing_settings_return_the_seeded_defaults(): void
    {
        $this->assertSame(15, $this->settings->getMinutes(AccountLinkExpirationSettings::KEY_INVITATION_LINK));
        $this->assertSame(15, $this->settings->getMinutes(AccountLinkExpirationSettings::KEY_RECOVERY_CODE));
        $this->assertSame(5, $this->settings->getMinutes(AccountLinkExpirationSettings::KEY_EMAIL_VERIFICATION));

        $this->assertSame([
            'invitation_link_minutes' => 15,
            'recovery_code_minutes' => 15,
            'email_verification_minutes' => 5,
        ], $this->settings->defaults());

        $this->assertDatabaseCount('system_settings', 0);
    }

    public function test_existing_setting_values_are_returned_independently(): void
    {
        SystemSetting::query()->create([
            'setting_key' => AccountLinkExpirationSettings::KEY_INVITATION_LINK,
            'setting_value' => '30',
        ]);

        SystemSetting::query()->create([
            'setting_key' => AccountLinkExpirationSettings::KEY_EMAIL_VERIFICATION,
            'setting_value' => '10',
        ]);

        $this->assertSame(30, $this->settings->getMinutes(AccountLinkExpirationSettings::KEY_INVITATION_LINK));
        $this->assertSame(15, $this->settings->getMinutes(AccountLinkExpirationSettings::KEY_RECOVERY_CODE));
        $this->assertSame(10, $this->settings->getMinutes(AccountLinkExpirationSettings::KEY_EMAIL_VERIFICATION));
    }

    public function test_invitation_link_setting_respects_its_own_bounds(): void
    {
        $this->assertSame(1, $this->settings->update(AccountLinkExpirationSettings::KEY_INVITATION_LINK, 1));
        $this->assertSame(60, $this->settings->update(AccountLinkExpirationSettings::KEY_INVITATION_LINK, 60));

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => AccountLinkExpirationSettings::KEY_INVITATION_LINK,
            'setting_value' => '60',
        ]);
    }

    public function test_invitation_link_setting_rejects_values_outside_its_bounds(): void
    {
        $this->expectException(ValidationException::class);

        $this->settings->update(AccountLinkExpirationSettings::KEY_INVITATION_LINK, 61);
    }

    public function test_recovery_code_setting_respects_its_own_bounds(): void
    {
        $this->assertSame(1, $this->settings->update(AccountLinkExpirationSettings::KEY_RECOVERY_CODE, 1));
        $this->assertSame(30, $this->settings->update(AccountLinkExpirationSettings::KEY_RECOVERY_CODE, 30));
    }

    public function test_recovery_code_setting_rejects_values_beyond_thirty_minutes(): void
    {
        $this->expectException(ValidationException::class);

        $this->settings->update(AccountLinkExpirationSettings::KEY_RECOVERY_CODE, 31);
    }

    public function test_email_verification_setting_rejects_values_beyond_sixty_minutes(): void
    {
        $this->expectException(ValidationException::class);

        $this->settings->update(AccountLinkExpirationSettings::KEY_EMAIL_VERIFICATION, 61);
    }

    public function test_independent_updates_do_not_disturb_other_keys(): void
    {
        $this->settings->update(AccountLinkExpirationSettings::KEY_RECOVERY_CODE, 25);

        $this->assertSame(15, $this->settings->getMinutes(AccountLinkExpirationSettings::KEY_INVITATION_LINK));
        $this->assertSame(5, $this->settings->getMinutes(AccountLinkExpirationSettings::KEY_EMAIL_VERIFICATION));
        $this->assertSame(25, $this->settings->getMinutes(AccountLinkExpirationSettings::KEY_RECOVERY_CODE));

        $this->assertDatabaseCount('system_settings', 1);
    }

    public function test_unknown_setting_keys_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->settings->update('email_link_expiration_minutes', 60);
    }

    public function test_updating_a_setting_audits_the_change(): void
    {
        $actor = User::factory()->create();

        $this->settings->update(AccountLinkExpirationSettings::KEY_INVITATION_LINK, 45, $actor);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $actor->getKey(),
            'action' => 'settings.changed',
        ]);

        $auditLog = AuditLog::query()->where('actor_user_id', $actor->getKey())->first();

        $this->assertSame([
            'setting_key' => AccountLinkExpirationSettings::KEY_INVITATION_LINK,
            'setting_value' => '15',
        ], $auditLog->before_values);

        $this->assertSame([
            'setting_key' => AccountLinkExpirationSettings::KEY_INVITATION_LINK,
            'setting_value' => '45',
        ], $auditLog->after_values);
    }

    public function test_updating_to_the_same_value_writes_and_audits_nothing(): void
    {
        $actor = User::factory()->create();

        $this->settings->update(AccountLinkExpirationSettings::KEY_INVITATION_LINK, 45, $actor);

        $this->settings->update(AccountLinkExpirationSettings::KEY_INVITATION_LINK, 45, $actor);

        $this->assertDatabaseCount('system_settings', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_invalid_values_never_touch_storage(): void
    {
        try {
            $this->settings->update(AccountLinkExpirationSettings::KEY_EMAIL_VERIFICATION, 0);
        } catch (ValidationException) {
        }

        $this->assertDatabaseCount('system_settings', 0);
    }
}
