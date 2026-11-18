<?php

namespace Tests\Feature\Settings;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceClientPermission;
use App\Models\User;
use App\Services\Authorization\OAuthScopes;
use App\Services\Authorization\ServiceClientAuthorizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AccountLinkExpirationHttpTest extends TestCase
{
    use RefreshDatabase;

    private const READ_ENDPOINT = '/api/v1/system-settings/account-link-expirations';

    private const WRITE_ENDPOINT = '/api/v1/system-settings/account-link-expirations';

    private const VIEW_PERMISSION = 'settings.account_link_expiration.view';

    // ------------------------------------------------------------------
    // Reads and independent updates
    // ------------------------------------------------------------------

    public function test_super_admin_reads_the_seeded_defaults(): void
    {
        $this->actingAsSuperAdmin()
            ->getJson(self::READ_ENDPOINT)
            ->assertOk()
            ->assertJson([
                'data' => [
                    'invitation_link_expiration_minutes' => 15,
                    'recovery_code_expiration_minutes' => 15,
                    'email_verification_expiration_minutes' => 5,
                ],
                'meta' => [],
                'links' => [],
            ]);
    }

    public function test_super_admin_updates_one_setting_without_touching_the_others(): void
    {
        $this->actingAsSuperAdmin()
            ->putJson(self::WRITE_ENDPOINT, ['recovery_code_expiration_minutes' => 30])
            ->assertOk()
            ->assertJson([
                'data' => [
                    'invitation_link_expiration_minutes' => 15,
                    'recovery_code_expiration_minutes' => 30,
                    'email_verification_expiration_minutes' => 5,
                ],
            ]);
    }

    public function test_out_of_bounds_values_are_rejected_with_the_own_key_bounds(): void
    {
        $actor = $this->actingAsSuperAdmin();

        $actor->putJson(self::WRITE_ENDPOINT, ['invitation_link_expiration_minutes' => 61])
            ->assertUnprocessable();

        $actor->putJson(self::WRITE_ENDPOINT, ['recovery_code_expiration_minutes' => 31])
            ->assertUnprocessable();

        $actor->putJson(self::WRITE_ENDPOINT, ['email_verification_expiration_minutes' => 61])
            ->assertUnprocessable();

        $this->assertDatabaseCount('system_settings', 0);
    }

    public function test_corrected_boundaries_are_accepted(): void
    {
        $actor = $this->actingAsSuperAdmin();

        $actor->putJson(self::WRITE_ENDPOINT, ['invitation_link_expiration_minutes' => 60])
            ->assertOk();

        $actor->putJson(self::WRITE_ENDPOINT, ['recovery_code_expiration_minutes' => 30])
            ->assertOk();

        $actor->putJson(self::WRITE_ENDPOINT, ['email_verification_expiration_minutes' => 1])
            ->assertOk();
    }

    // ------------------------------------------------------------------
    // Authorization
    // ------------------------------------------------------------------

    public function test_system_admin_with_the_view_permission_can_read(): void
    {
        $user = $this->createUserWithRole('SYSTEM_ADMIN', 'system');

        $this->assignViewPermission($user->role);

        Passport::actingAs($user);

        $this->getJson(self::READ_ENDPOINT)->assertOk();
    }

    public function test_system_admin_without_the_view_permission_is_denied(): void
    {
        $user = $this->createUserWithRole('SYSTEM_ADMIN', 'system');

        Passport::actingAs($user);

        $this->getJson(self::READ_ENDPOINT)->assertForbidden();
    }

    public function test_system_admin_cannot_update_the_settings(): void
    {
        $user = $this->createUserWithRole('SYSTEM_ADMIN', 'system');

        $this->assignViewPermission($user->role);

        Passport::actingAs($user);

        $this->putJson(self::WRITE_ENDPOINT, ['invitation_link_expiration_minutes' => 30])
            ->assertForbidden();
    }

    public function test_super_admin_can_update_without_the_view_permission(): void
    {
        $this->actingAsSuperAdmin()
            ->putJson(self::WRITE_ENDPOINT, ['invitation_link_expiration_minutes' => 60])
            ->assertOk();
    }

    public function test_guests_are_unauthenticated(): void
    {
        $this->getJson(self::READ_ENDPOINT)
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);

        $this->putJson(self::WRITE_ENDPOINT, ['invitation_link_expiration_minutes' => 60])
            ->assertUnauthorized();
    }

    // ------------------------------------------------------------------
    // Machine clients follow the existing system-settings API rules
    // ------------------------------------------------------------------

    public function test_service_client_with_scope_and_permission_can_read(): void
    {
        $client = $this->grantServicePermission(
            Client::factory()->asClientCredentials()->create(),
        );

        $this->readAsServiceClient($client, self::READ_ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.invitation_link_expiration_minutes', 15);
    }

    public function test_service_client_without_permission_is_denied(): void
    {
        $client = Client::factory()->asClientCredentials()->create();

        $this->readAsServiceClient($client, self::READ_ENDPOINT)->assertForbidden();
    }

    public function test_service_client_without_scope_is_denied(): void
    {
        $client = $this->grantServicePermission(
            Client::factory()->asClientCredentials()->create(),
        );

        $this->readAsServiceClient($client, self::READ_ENDPOINT, '')->assertForbidden();
    }

    public function test_machine_client_write_access_is_denied(): void
    {
        $client = $this->grantServicePermission(
            Client::factory()->asClientCredentials()->create(),
        );

        $this->writeAsServiceClient($client, self::WRITE_ENDPOINT)
            ->assertForbidden()
            ->assertJson([
                'message' => 'Machine clients cannot update system settings.',
            ]);
    }

    // ------------------------------------------------------------------
    // Auditing
    // ------------------------------------------------------------------

    public function test_a_changed_value_audits_the_change_with_the_actor(): void
    {
        $actor = $this->superAdmin();

        $this->actingAsSuperAdmin($actor)
            ->putJson(self::WRITE_ENDPOINT, ['email_verification_expiration_minutes' => 10])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $actor->getKey(),
            'action' => 'settings.changed',
        ]);

        $auditLog = AuditLog::query()->sole();

        $this->assertSame([
            'setting_key' => 'accounts.email_verification_expiration_minutes',
            'setting_value' => '5',
        ], $auditLog->before_values);

        $this->assertSame([
            'setting_key' => 'accounts.email_verification_expiration_minutes',
            'setting_value' => '10',
        ], $auditLog->after_values);
    }

    public function test_an_unchanged_value_creates_no_audit_record(): void
    {
        $this->actingAsSuperAdmin()
            ->putJson(self::WRITE_ENDPOINT, ['invitation_link_expiration_minutes' => 15])
            ->assertOk();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_updates_without_a_body_write_and_audit_nothing(): void
    {
        $this->actingAsSuperAdmin()
            ->putJson(self::WRITE_ENDPOINT, [])
            ->assertOk()
            ->assertJsonPath('data.invitation_link_expiration_minutes', 15);

        $this->assertDatabaseCount('audit_logs', 0);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function superAdmin(): User
    {
        $role = Role::query()->create([
            'name' => 'SUPER_ADMIN',
            'code' => 'SUPER_ADMIN',
            'scope' => 'system',
        ]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * Act as the super admin through the API guard, matching the middleware
     * resolution path for human tokens.
     */
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

    private function assignViewPermission(Role $role): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['code' => self::VIEW_PERMISSION],
            [
                'name' => 'View Account Link Expiration Settings',
                'description' => 'Allows viewing the configured expiration durations for invitation, recovery-code, and account email-verification links.',
            ],
        );

        $role->permissions()->attach($permission);
    }

    private function grantServicePermission(Client $client): Client
    {
        ServiceClientPermission::query()->create([
            'oauth_client_id' => $client->getKey(),
            'permission' => ServiceClientAuthorizer::SYSTEM_SETTINGS_READ,
        ]);

        return $client;
    }

    private function readAsServiceClient(
        Client $client,
        string $endpoint,
        string $scope = OAuthScopes::SYSTEM_SETTINGS_READ
    ): TestResponse {
        return $this->withToken($this->issueClientCredentialsToken($client, $scope))
            ->getJson($endpoint);
    }

    private function writeAsServiceClient(Client $client, string $endpoint): TestResponse
    {
        return $this->withToken($this->issueClientCredentialsToken($client, ''))
            ->putJson($endpoint, ['invitation_link_expiration_minutes' => 30]);
    }

    private function issueClientCredentialsToken(Client $client, string $scope = OAuthScopes::SYSTEM_SETTINGS_READ): string
    {
        $response = $this->post('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
            'scope' => $scope,
        ]);

        $this->assertTrue(
            $response->getStatusCode() === 200,
            'Expected a client-credentials token to be issued: '.$response->json('error'),
        );

        return (string) $response->json('access_token');
    }
}
