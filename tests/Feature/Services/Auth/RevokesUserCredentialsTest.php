<?php

namespace Tests\Feature\Services\Auth;

use App\Models\User;
use App\Services\Auth\RevokesUserCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\AuthCode;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Tests\TestCase;

class RevokesUserCredentialsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Run the service against the real migrated Passport tables.
     *
     * @return array{access_tokens: int, refresh_tokens: int, authorization_codes: int}
     */
    private function revoke(User $user): array
    {
        return (new RevokesUserCredentials)->revokeForUser($user->getKey());
    }

    /**
     * Create an access token through the installed Passport model.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createAccessToken(?int $userId, array $overrides = []): Token
    {
        return Token::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'client_id' => (string) Str::uuid(),
            'revoked' => false,
        ], $overrides));
    }

    /**
     * Create a refresh token linked to the given access token.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createRefreshToken(Token $accessToken, array $overrides = []): RefreshToken
    {
        return RefreshToken::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'access_token_id' => $accessToken->getKey(),
            'revoked' => false,
        ], $overrides));
    }

    /**
     * Create an authorization code through the installed Passport model.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createAuthCode(?int $userId, array $overrides = []): AuthCode
    {
        return AuthCode::query()->create(array_merge([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'client_id' => (string) Str::uuid(),
            'revoked' => false,
        ], $overrides));
    }

    public function test_revokes_active_access_tokens_of_the_specified_user(): void
    {
        $user = User::factory()->create();
        $accessToken = $this->createAccessToken($user->getKey());

        $counts = $this->revoke($user);

        $this->assertSame(['access_tokens' => 1, 'refresh_tokens' => 0, 'authorization_codes' => 0], $counts);
        $this->assertTrue($accessToken->fresh()->revoked);
    }

    public function test_leaves_another_users_access_tokens_untouched(): void
    {
        $target = User::factory()->create();
        $other = User::factory()->create();
        $targetToken = $this->createAccessToken($target->getKey());
        $otherToken = $this->createAccessToken($other->getKey());

        $this->revoke($target);

        $this->assertTrue($targetToken->fresh()->revoked);
        $this->assertFalse($otherToken->fresh()->revoked);
    }

    public function test_revokes_refresh_tokens_linked_to_the_users_access_tokens(): void
    {
        $user = User::factory()->create();
        $accessToken = $this->createAccessToken($user->getKey());
        $refreshToken = $this->createRefreshToken($accessToken);

        $counts = $this->revoke($user);

        $this->assertSame(1, $counts['access_tokens']);
        $this->assertSame(1, $counts['refresh_tokens']);
        $this->assertTrue($refreshToken->fresh()->revoked);
    }

    public function test_revokes_outstanding_authorization_codes_of_the_specified_user(): void
    {
        $user = User::factory()->create();
        $authCode = $this->createAuthCode($user->getKey());

        $counts = $this->revoke($user);

        $this->assertSame(1, $counts['authorization_codes']);
        $this->assertTrue($authCode->fresh()->revoked);
    }

    public function test_revokes_refresh_tokens_linked_to_already_revoked_access_tokens(): void
    {
        $user = User::factory()->create();
        $revokedAccessToken = $this->createAccessToken($user->getKey(), ['revoked' => true]);
        $refreshToken = $this->createRefreshToken($revokedAccessToken);

        $counts = $this->revoke($user);

        $this->assertSame(0, $counts['access_tokens']);
        $this->assertSame(1, $counts['refresh_tokens']);
        $this->assertTrue($refreshToken->fresh()->revoked);
    }

    public function test_returns_zero_counts_and_keeps_credentials_revoked_when_repeated(): void
    {
        $user = User::factory()->create();
        $accessToken = $this->createAccessToken($user->getKey());
        $refreshToken = $this->createRefreshToken($accessToken);
        $authCode = $this->createAuthCode($user->getKey());

        $firstCounts = $this->revoke($user);
        $secondCounts = $this->revoke($user);

        $this->assertSame(['access_tokens' => 1, 'refresh_tokens' => 1, 'authorization_codes' => 1], $firstCounts);
        $this->assertSame(['access_tokens' => 0, 'refresh_tokens' => 0, 'authorization_codes' => 0], $secondCounts);
        $this->assertTrue($accessToken->fresh()->revoked);
        $this->assertTrue($refreshToken->fresh()->revoked);
        $this->assertTrue($authCode->fresh()->revoked);
    }

    public function test_returns_zero_counts_when_the_user_has_no_credentials(): void
    {
        $user = User::factory()->create();

        $counts = $this->revoke($user);

        $this->assertSame(['access_tokens' => 0, 'refresh_tokens' => 0, 'authorization_codes' => 0], $counts);
    }

    public function test_leaves_tokens_without_a_user_id_untouched(): void
    {
        $user = User::factory()->create();
        $machineToken = $this->createAccessToken(null);

        $counts = $this->revoke($user);

        $this->assertSame(0, $counts['access_tokens']);
        $this->assertNull($machineToken->fresh()->user_id);
        $this->assertFalse($machineToken->fresh()->revoked);
    }

    public function test_returns_exactly_the_three_aggregate_count_keys_as_integers(): void
    {
        $user = User::factory()->create();
        $accessToken = $this->createAccessToken($user->getKey());
        $this->createRefreshToken($accessToken);
        $this->createAuthCode($user->getKey());

        $counts = $this->revoke($user);

        $this->assertSame(['access_tokens' => 1, 'refresh_tokens' => 1, 'authorization_codes' => 1], $counts);
        $this->assertIsInt($counts['access_tokens']);
        $this->assertIsInt($counts['refresh_tokens']);
        $this->assertIsInt($counts['authorization_codes']);
    }

    public function test_revocations_roll_back_with_the_callers_transaction(): void
    {
        $user = User::factory()->create();
        $accessToken = $this->createAccessToken($user->getKey());
        $refreshToken = $this->createRefreshToken($accessToken);
        $authCode = $this->createAuthCode($user->getKey());

        DB::beginTransaction();

        $this->revoke($user);
        $this->assertTrue($accessToken->fresh()->revoked);

        DB::rollBack();

        $this->assertFalse($accessToken->fresh()->revoked);
        $this->assertFalse($refreshToken->fresh()->revoked);
        $this->assertFalse($authCode->fresh()->revoked);
    }
}
