<?php

namespace Tests\Feature\Settings;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class EmailVerificationLimitSettingsHttpTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/system-settings/email-verification-limits';

    private const VIEW_PERMISSION = 'settings.account_link_expiration.view';

    public function test_super_admin_reads_the_default_ip_limit(): void
    {
        $this->actingAsSuperAdmin()
            ->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertExactJson([
                'data' => ['email_verification_ip_hourly_limit' => 10],
                'meta' => [],
                'links' => [],
            ]);
    }

    public function test_super_admin_updates_the_ip_limit_and_it_is_audited(): void
    {
        $actor = $this->superAdmin();

        $this->actingAsSuperAdmin($actor)
            ->putJson(self::ENDPOINT, ['email_verification_ip_hourly_limit' => 25])
            ->assertOk()
            ->assertJsonPath('data.email_verification_ip_hourly_limit', 25);

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'accounts.email_verification_ip_hourly_limit',
            'setting_value' => '25',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $actor->getKey(),
            'action' => 'settings.changed',
        ]);
    }

    public function test_out_of_bounds_values_are_rejected(): void
    {
        $actor = $this->actingAsSuperAdmin();

        $actor->putJson(self::ENDPOINT, ['email_verification_ip_hourly_limit' => 0])
            ->assertUnprocessable();

        $actor->putJson(self::ENDPOINT, ['email_verification_ip_hourly_limit' => 101])
            ->assertUnprocessable();

        $this->assertDatabaseCount('system_settings', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_an_unchanged_value_writes_and_audits_nothing(): void
    {
        $this->actingAsSuperAdmin()
            ->putJson(self::ENDPOINT, ['email_verification_ip_hourly_limit' => 10])
            ->assertOk();

        $this->assertDatabaseCount('system_settings', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_system_admin_with_the_view_permission_can_read_but_not_write(): void
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SYSTEM_ADMIN', 'scope' => 'system'],
            ['name' => 'System Admin'],
        );

        $role->permissions()->attach(Permission::query()->firstOrCreate(
            ['code' => self::VIEW_PERMISSION],
            ['name' => 'View Account Link Expiration Settings'],
        ));

        $actor = User::factory()->create(['role_id' => $role->id]);

        Passport::actingAs($actor);

        $this->getJson(self::ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.email_verification_ip_hourly_limit', 10);

        $this->putJson(self::ENDPOINT, ['email_verification_ip_hourly_limit' => 20])
            ->assertForbidden();
    }

    private function superAdmin(): User
    {
        $role = Role::query()->create([
            'name' => 'SUPER_ADMIN',
            'code' => 'SUPER_ADMIN',
            'scope' => 'system',
        ]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function actingAsSuperAdmin(?User $actor = null): self
    {
        Passport::actingAs($actor ?? $this->superAdmin());

        return $this;
    }
}
