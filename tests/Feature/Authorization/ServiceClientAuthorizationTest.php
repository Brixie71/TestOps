<?php

namespace Tests\Feature\Authorization;

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
use Laravel\Passport\Token;
use Tests\TestCase;

class ServiceClientAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private const READ_ENDPOINT = '/api/v1/system-settings/email-link-expiration';

    private const WRITE_ENDPOINT = '/api/v1/system-settings/email-link-expiration';

    // ------------------------------------------------------------------
    // Service client (machine principal) authorization
    // ------------------------------------------------------------------

    public function test_service_client_with_scope_and_permission_can_read_setting(): void
    {
        $client = $this->grantServicePermission(
            Client::factory()->asClientCredentials()->create(),
        );

        $this->readSettingAsServiceClient($client)
            ->assertOk()
            ->assertJson([
                'data' => [
                    'email_link_expiration_minutes' => 60,
                ],
                'meta' => [],
                'links' => [],
            ]);
    }

    public function test_service_client_without_permission_is_denied(): void
    {
        $client = Client::factory()->asClientCredentials()->create();

        $this->readSettingAsServiceClient($client)
            ->assertForbidden();
    }

    public function test_service_client_without_required_scope_is_denied(): void
    {
        $client = $this->grantServicePermission(
            Client::factory()->asClientCredentials()->create(),
        );

        $this->readSettingAsServiceClient($client, '')
            ->assertForbidden();
    }

    public function test_client_without_client_credentials_grant_cannot_authorize_machine_access(): void
    {
        // A client configured for other grants only cannot authorize machine
        // access, even when the token carries the expected scope and the
        // client holds the application permission.
        $client = $this->grantServicePermission(
            Client::factory()->create(),
        );

        $this->assertFalse($client->hasGrantType('client_credentials'));

        Passport::actingAsClient($client, [OAuthScopes::SYSTEM_SETTINGS_READ]);

        $this->withToken('not-a-real-jwt')
            ->getJson(self::READ_ENDPOINT)
            ->assertForbidden();
    }

    public function test_machine_client_write_access_is_denied(): void
    {
        $client = $this->grantServicePermission(
            Client::factory()->asClientCredentials()->create(),
        );

        $this->updateSettingAsServiceClient($client, 300)
            ->assertForbidden()
            ->assertJson([
                'message' => 'Machine clients cannot update system settings.',
            ]);
    }

    public function test_service_client_is_never_treated_as_a_human_administrator(): void
    {
        // A provisioned machine client keeps only its service-permission
        // grants even though no role or human user ever exists.
        $client = $this->grantServicePermission(
            Client::factory()->asClientCredentials()->create(),
        );

        $this->readSettingAsServiceClient($client)->assertOk();

        $this->updateSettingAsServiceClient($client, 300)->assertForbidden();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_unprovisioned_permission_is_denied_even_when_token_has_scope(): void
    {
        // Scope without the application permission must not grant access.
        $client = Client::factory()->asClientCredentials()->create();

        ServiceClientPermission::query()->create([
            'oauth_client_id' => $client->getKey(),
            'permission' => 'not-relevant-permission',
        ]);

        $this->readSettingAsServiceClient($client)->assertForbidden();
    }

    public function test_revoked_service_client_cannot_access_protected_endpoints(): void
    {
        $client = $this->grantServicePermission(
            Client::factory()->asClientCredentials()->create(),
        );

        $token = $this->issueClientCredentialsToken($client);

        Client::query()->whereKey($client->getKey())->update(['revoked' => true]);

        $this->withToken($token)
            ->getJson(self::READ_ENDPOINT)
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    public function test_revoked_access_token_cannot_access_protected_endpoints(): void
    {
        $client = $this->grantServicePermission(
            Client::factory()->asClientCredentials()->create(),
        );

        $token = $this->issueClientCredentialsToken($client);

        $accessTokenId = Token::query()->where('client_id', $client->getKey())->value('id');

        Token::query()->whereKey($accessTokenId)->update(['revoked' => true]);

        $this->withToken($token)
            ->getJson(self::READ_ENDPOINT)
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    // ------------------------------------------------------------------
    // Human authorization on the API (existing RBAC rules, unchanged)
    // ------------------------------------------------------------------

    public function test_super_admin_can_read_setting_via_api(): void
    {
        $user = $this->createUserWithRole('SUPER_ADMIN', 'system');

        Passport::actingAs($user);

        $this->getJson(self::READ_ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.email_link_expiration_minutes', 60);
    }

    public function test_super_admin_with_view_permission_for_system_roles_also_reads_setting(): void
    {
        $user = $this->createUserWithRole('SYSTEM_ADMIN', 'system');

        $this->assignViewPermission($user->role);

        Passport::actingAs($user);

        $this->getJson(self::READ_ENDPOINT)
            ->assertOk()
            ->assertJsonPath('data.email_link_expiration_minutes', 60);
    }

    public function test_other_system_role_with_view_permission_can_read_setting(): void
    {
        $user = $this->createUserWithRole('QA_SUPERVISOR', 'system');

        $this->assignViewPermission($user->role);

        Passport::actingAs($user);

        $this->getJson(self::READ_ENDPOINT)->assertOk();
    }

    public function test_system_admin_without_view_permission_is_denied_via_api(): void
    {
        $user = $this->createUserWithRole('SYSTEM_ADMIN', 'system');

        Passport::actingAs($user);

        $this->getJson(self::READ_ENDPOINT)->assertForbidden();
    }

    public function test_project_scoped_role_with_view_permission_is_denied_via_api(): void
    {
        $user = $this->createUserWithRole('PROJECT_ADMIN', 'project');

        $this->assignViewPermission($user->role);

        Passport::actingAs($user);

        $this->getJson(self::READ_ENDPOINT)->assertForbidden();
    }

    public function test_only_super_admin_can_update_setting_via_api(): void
    {
        $user = $this->createUserWithRole('SUPER_ADMIN', 'system');

        Passport::actingAs($user);

        $this->putJson(self::WRITE_ENDPOINT, ['minutes' => 300])
            ->assertOk()
            ->assertJsonPath('data.email_link_expiration_minutes', 300);

        $this->assertDatabaseHas('system_settings', [
            'setting_key' => 'email_link_expiration_minutes',
            'setting_value' => '300',
        ]);
    }

    public function test_system_admin_cannot_update_setting_via_api(): void
    {
        $user = $this->createUserWithRole('SYSTEM_ADMIN', 'system');

        $this->assignViewPermission($user->role);

        Passport::actingAs($user);

        $this->putJson(self::WRITE_ENDPOINT, ['minutes' => 300])
            ->assertForbidden();
    }

    public function test_update_setting_rejects_out_of_range_minutes(): void
    {
        $user = $this->createUserWithRole('SUPER_ADMIN', 'system');

        Passport::actingAs($user);

        $this->putJson(self::WRITE_ENDPOINT, ['minutes' => 5000])->assertUnprocessable();

        $this->assertDatabaseCount('system_settings', 0);
    }

    public function test_requests_without_access_tokens_are_unauthenticated(): void
    {
        $this->getJson(self::READ_ENDPOINT)
            ->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);

        $this->putJson(self::WRITE_ENDPOINT, ['minutes' => 300])
            ->assertUnauthorized();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Assign the initial service permission and keep the client active.
     */
    private function grantServicePermission(Client $client): Client
    {
        ServiceClientPermission::query()->create([
            'oauth_client_id' => $client->getKey(),
            'permission' => ServiceClientAuthorizer::SYSTEM_SETTINGS_READ,
        ]);

        return $client;
    }

    /**
     * Call the read endpoint with a freshly issued client-credentials token.
     */
    private function readSettingAsServiceClient(Client $client, string $scope = OAuthScopes::SYSTEM_SETTINGS_READ): TestResponse
    {
        $token = $this->issueClientCredentialsToken($client, $scope);

        return $this->withToken($token)->getJson(self::READ_ENDPOINT);
    }

    /**
     * Call the update endpoint with a freshly issued client-credentials token.
     */
    private function updateSettingAsServiceClient(Client $client, int $minutes): TestResponse
    {
        $token = $this->issueClientCredentialsToken($client);

        return $this->withToken($token)
            ->putJson(self::WRITE_ENDPOINT, ['minutes' => $minutes]);
    }

    /**
     * Issue a client-credentials access token through the real token endpoint.
     */
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

    private function createUserWithRole(string $code, string $scope): User
    {
        $role = Role::query()->create([
            'name' => $code,
            'code' => $code,
            'scope' => $scope,
        ]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    /**
     * Attach the existing human view permission to a role, matching the
     * established system-role authorization behavior.
     */
    private function assignViewPermission(Role $role): void
    {
        $permission = Permission::query()->firstOrCreate(
            ['code' => 'settings.email_link_expiration.view'],
            [
                'name' => 'View Email Link Expiration Setting',
                'description' => 'Allows viewing the configured expiration duration for invitation and email-change links.',
            ],
        );

        $role->permissions()->attach($permission);
    }
}
