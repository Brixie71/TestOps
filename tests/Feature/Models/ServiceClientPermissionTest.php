<?php

namespace Tests\Feature\Models;

use App\Models\ServiceClientPermission;
use App\Services\Authorization\ServiceClientAuthorizer;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;
use Tests\TestCase;

class ServiceClientPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_permission_requires_a_valid_oauth_client(): void
    {
        $this->expectException(QueryException::class);

        ServiceClientPermission::query()->create([
            'oauth_client_id' => (string) fake()->uuid(),
            'permission' => ServiceClientAuthorizer::SYSTEM_SETTINGS_READ,
        ]);
    }

    public function test_service_permission_reference_resolves_to_the_oauth_client(): void
    {
        $client = Client::factory()->asClientCredentials()->create();

        ServiceClientPermission::query()->create([
            'oauth_client_id' => $client->getKey(),
            'permission' => 'system-settings.read',
        ]);

        $permission = ServiceClientPermission::query()->first();

        $this->assertSame($client->getKey(), $permission->oauthClient->getKey());
    }

    public function test_duplicate_client_permission_assignment_is_rejected(): void
    {
        $client = Client::factory()->asClientCredentials()->create();

        ServiceClientPermission::query()->create([
            'oauth_client_id' => $client->getKey(),
            'permission' => 'system-settings.read',
        ]);

        $this->expectException(QueryException::class);

        ServiceClientPermission::query()->create([
            'oauth_client_id' => $client->getKey(),
            'permission' => 'system-settings.read',
        ]);
    }

    public function test_deleting_an_oauth_client_cascades_its_service_permissions(): void
    {
        $client = Client::factory()->asClientCredentials()->create();

        ServiceClientPermission::query()->create([
            'oauth_client_id' => $client->getKey(),
            'permission' => 'system-settings.read',
        ]);

        $client->delete();

        $this->assertDatabaseEmpty('service_client_permissions');
    }

    public function test_same_permission_can_be_granted_to_different_clients(): void
    {
        $first = Client::factory()->asClientCredentials()->create();
        $second = Client::factory()->asClientCredentials()->create();

        foreach ([$first, $second] as $client) {
            ServiceClientPermission::query()->create([
                'oauth_client_id' => $client->getKey(),
                'permission' => 'system-settings.read',
            ]);
        }

        $this->assertSame(2, DB::table('service_client_permissions')->count());
    }
}
