<?php

namespace Tests\Feature\Settings;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Regression coverage for the existing 60-minute email-link expiration
 * setting after the account-settings work: behavior, bounds, and now the
 * new settings.changed auditing.
 */
class EmailLinkExpirationAuditTest extends TestCase
{
    use RefreshDatabase;

    private const READ_ENDPOINT = '/api/v1/system-settings/email-link-expiration';

    private const WRITE_ENDPOINT = '/api/v1/system-settings/email-link-expiration';

    public function test_super_admin_reads_the_unchanged_default(): void
    {
        $this->actingAsSuperAdmin()
            ->getJson(self::READ_ENDPOINT)
            ->assertOk()
            ->assertJson([
                'data' => ['email_link_expiration_minutes' => 60],
                'meta' => [],
                'links' => [],
            ]);
    }

    public function test_super_admin_still_updates_within_the_unchanged_bounds(): void
    {
        $actor = $this->actingAsSuperAdmin();

        $actor->putJson(self::WRITE_ENDPOINT, ['minutes' => 1440])->assertOk();

        $actor->putJson(self::WRITE_ENDPOINT, ['minutes' => 1])->assertOk();

        $actor->putJson(self::WRITE_ENDPOINT, ['minutes' => 1441])->assertUnprocessable();

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'email_link_expiration_minutes',
            'setting_value' => '1',
        ]);
    }

    public function test_a_changed_update_audits_settings_changed(): void
    {
        $actor = $this->superAdmin();

        $this->actingAsSuperAdmin($actor)
            ->putJson(self::WRITE_ENDPOINT, ['minutes' => 120])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $actor->getKey(),
            'action' => 'settings.changed',
        ]);

        $auditLog = AuditLog::query()->sole();

        $this->assertSame([
            'setting_key' => 'email_link_expiration_minutes',
            'setting_value' => '60',
        ], $auditLog->before_values);

        $this->assertSame([
            'setting_key' => 'email_link_expiration_minutes',
            'setting_value' => '120',
        ], $auditLog->after_values);
    }

    public function test_setting_the_same_value_does_not_duplicate_audit_records(): void
    {
        $this->actingAsSuperAdmin()
            ->putJson(self::WRITE_ENDPOINT, ['minutes' => 60])
            ->assertOk();

        $this->actingAsSuperAdmin()
            ->putJson(self::WRITE_ENDPOINT, ['minutes' => 60])
            ->assertOk();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_only_super_admins_can_update(): void
    {
        $systemAdmin = $this->createUserWithRole('SYSTEM_ADMIN', 'system');

        Passport::actingAs($systemAdmin);

        $this->putJson(self::WRITE_ENDPOINT, ['minutes' => 120])
            ->assertForbidden();

        $this->assertDatabaseCount('system_settings', 0);
    }

    private function superAdmin(): User
    {
        return $this->createUserWithRole('SUPER_ADMIN', 'system');
    }

    private function actingAsSuperAdmin(?User $actor = null): self
    {
        Passport::actingAs($actor ?? $this->superAdmin());

        return $this;
    }

    private function createUserWithRole(string $code, string $scope): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => $code, 'scope' => $scope],
            ['name' => $code],
        );

        return User::factory()->create(['role_id' => $role->id]);
    }
}
