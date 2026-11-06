<?php

namespace Tests\Feature\Authentication;

use App\Services\Authorization\OAuthScopes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Client;
use Tests\TestCase;

class OAuthScopeIssuanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_credentials_token_receives_requested_read_scope(): void
    {
        $client = Client::factory()->asClientCredentials()->create();

        $response = $this->requestClientCredentialsToken($client, OAuthScopes::SYSTEM_SETTINGS_READ);

        $response->assertSuccessful();

        $this->assertSame(
            [OAuthScopes::SYSTEM_SETTINGS_READ],
            $this->persistedTokenScopes(),
        );
    }

    public function test_client_credentials_token_receives_no_scope_when_none_is_requested(): void
    {
        $client = Client::factory()->asClientCredentials()->create();

        $response = $this->requestClientCredentialsToken($client, '');

        $response->assertSuccessful();

        $this->assertSame([], $this->persistedTokenScopes());
    }

    public function test_client_credentials_token_cannot_receive_write_scope(): void
    {
        $client = Client::factory()->asClientCredentials()->create();

        $response = $this->requestClientCredentialsToken(
            $client,
            OAuthScopes::SYSTEM_SETTINGS_READ.' '.OAuthScopes::SYSTEM_SETTINGS_WRITE,
        );

        // Issuance succeeds, but the issued token keeps only the machine
        // allowlist scopes: machine-client write access stays disabled.
        $response->assertSuccessful();

        $this->assertSame(
            [OAuthScopes::SYSTEM_SETTINGS_READ],
            $this->persistedTokenScopes(),
        );
    }

    public function test_unregistered_scopes_cannot_be_granted(): void
    {
        $client = Client::factory()->asClientCredentials()->create();

        $response = $this->requestClientCredentialsToken($client, 'not-a-registered-scope');

        $response->assertBadRequest();

        $this->assertSame('invalid_scope', $response->json('error'));

        $this->assertDatabaseCount('oauth_access_tokens', 0);
    }

    /**
     * Issue a client-credentials access token through the real token endpoint.
     */
    private function requestClientCredentialsToken(Client $client, string $scope): TestResponse
    {
        return $this->post('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $client->getKey(),
            'client_secret' => $client->plainSecret,
            'scope' => $scope,
        ]);
    }

    /**
     * @return string[]
     */
    private function persistedTokenScopes(): array
    {
        $token = DB::table('oauth_access_tokens')->first();

        if ($token === null) {
            return [];
        }

        return array_values(json_decode((string) $token->scopes, true) ?? []);
    }
}
