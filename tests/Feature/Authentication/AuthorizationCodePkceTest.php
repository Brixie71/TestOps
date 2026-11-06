<?php

namespace Tests\Feature\Authentication;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AuthorizationCodePkceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Passport::authorizationView(
            fn (array $parameters) => response('consent')
        );
    }

    public function test_confidential_client_cannot_authorize_without_pkce(): void
    {
        $client = Client::factory()->create();

        $this->getJson('/oauth/authorize?'.http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => $client->redirect_uris[0],
            'response_type' => 'code',
            'state' => 'test-state',
            'prompt' => 'consent',
        ]))
            ->assertBadRequest()
            ->assertJsonPath('error', 'invalid_request');
    }

    public function test_public_client_cannot_authorize_without_pkce(): void
    {
        $client = Client::factory()->asPublic()->create();

        $this->getJson('/oauth/authorize?'.http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => $client->redirect_uris[0],
            'response_type' => 'code',
            'state' => 'test-state',
            'prompt' => 'consent',
        ]))
            ->assertBadRequest()
            ->assertJsonPath('error', 'invalid_request');
    }

    public function test_confidential_client_can_exchange_authorization_code_with_s256_pkce(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->create();
        $verifier = str_repeat('a', 43);

        $code = $this->issueAuthorizationCode($client, $user, $verifier);

        $this->exchangeAuthorizationCode($client, $code, $verifier)
            ->assertOk()
            ->assertJsonStructure([
                'token_type',
                'expires_in',
                'access_token',
                'refresh_token',
            ]);
    }

    public function test_public_client_can_exchange_authorization_code_with_s256_pkce(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->asPublic()->create();
        $verifier = str_repeat('a', 43);

        $code = $this->issueAuthorizationCode($client, $user, $verifier);

        $this->exchangeAuthorizationCode($client, $code, $verifier)
            ->assertOk()
            ->assertJsonStructure([
                'token_type',
                'expires_in',
                'access_token',
                'refresh_token',
            ]);
    }

    public function test_authorization_code_exchange_rejects_incorrect_pkce_verifier(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->asPublic()->create();
        $verifier = str_repeat('a', 43);

        $code = $this->issueAuthorizationCode($client, $user, $verifier);

        $this->exchangeAuthorizationCode($client, $code, str_repeat('b', 43))
            ->assertBadRequest()
            ->assertJsonPath('error', 'invalid_grant');
    }

    public function test_authorization_code_exchange_rejects_missing_pkce_verifier(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->asPublic()->create();
        $verifier = str_repeat('a', 43);

        $code = $this->issueAuthorizationCode($client, $user, $verifier);

        $this->postJson('/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->getKey(),
            'redirect_uri' => $client->redirect_uris[0],
            'code' => $code,
        ])
            ->assertBadRequest()
            ->assertJsonPath('error', 'invalid_request');
    }

    public function test_authorization_code_cannot_be_reused(): void
    {
        $user = User::factory()->create();
        $client = Client::factory()->asPublic()->create();
        $verifier = str_repeat('a', 43);

        $code = $this->issueAuthorizationCode($client, $user, $verifier);

        $this->exchangeAuthorizationCode($client, $code, $verifier)
            ->assertOk();

        $this->exchangeAuthorizationCode($client, $code, $verifier)
            ->assertBadRequest()
            ->assertJsonPath('error', 'invalid_grant');
    }

    private function issueAuthorizationCode(
        Client $client,
        User $user,
        string $codeVerifier,
    ): string {
        $this->actingAs($user, 'web');

        $authorizationResponse = $this->get('/oauth/authorize?'.http_build_query([
            'client_id' => $client->getKey(),
            'redirect_uri' => $client->redirect_uris[0],
            'response_type' => 'code',
            'state' => 'test-state',
            'code_challenge' => $this->s256Challenge($codeVerifier),
            'code_challenge_method' => 'S256',
            'prompt' => 'consent',
        ]));

        $authorizationResponse->assertOk();

        $authToken = $this->app['session.store']->get('authToken');

        $this->assertIsString($authToken);
        $this->assertNotEmpty($authToken);

        $approvalResponse = $this->post('/oauth/authorize', [
            'auth_token' => $authToken,
        ]);

        $approvalResponse->assertRedirect();

        $location = $approvalResponse->headers->get('Location');

        $this->assertIsString($location);

        parse_str(
            (string) parse_url($location, PHP_URL_QUERY),
            $query
        );

        $this->assertArrayHasKey('code', $query);
        $this->assertSame('test-state', $query['state'] ?? null);

        return $query['code'];
    }

    private function exchangeAuthorizationCode(
        Client $client,
        string $code,
        string $codeVerifier,
    ) {
        $payload = [
            'grant_type' => 'authorization_code',
            'client_id' => $client->getKey(),
            'redirect_uri' => $client->redirect_uris[0],
            'code' => $code,
            'code_verifier' => $codeVerifier,
        ];

        if ($client->confidential()) {
            $payload['client_secret'] = $client->plainSecret;
        }

        return $this->postJson('/oauth/token', $payload);
    }

    private function s256Challenge(string $verifier): string
    {
        return rtrim(
            strtr(
                base64_encode(hash('sha256', $verifier, true)),
                '+/',
                '-_'
            ),
            '='
        );
    }
}
