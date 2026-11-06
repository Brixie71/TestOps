<?php

namespace Tests\Feature\Authentication;

use App\Http\Middleware\EnforceAuthorizationCodePkce;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthorizationCodePkceMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(EnforceAuthorizationCodePkce::class)
            ->get('/_test/oauth/authorize', function () {
                return response()->json([
                    'message' => 'Authorization request accepted',
                ]);
            })
            ->name('passport.authorizations.authorize');

        Route::middleware(EnforceAuthorizationCodePkce::class)
            ->post('/_test/oauth/authorize', function () {
                return response()->json([
                    'message' => 'Authorization approval accepted',
                ]);
            })
            ->name('passport.authorizations.approve');

        Route::middleware(EnforceAuthorizationCodePkce::class)
            ->post('/_test/oauth/token', function () {
                return response()->json([
                    'message' => 'Token request accepted',
                ]);
            })
            ->name('passport.token');
    }

    public function test_authorization_request_without_code_challenge_is_rejected(): void
    {
        $this->getJson('/_test/oauth/authorize')
            ->assertBadRequest()
            ->assertJsonPath('error', 'invalid_request');
    }

    public function test_authorization_request_without_challenge_method_is_rejected(): void
    {
        $this->getJson('/_test/oauth/authorize?code_challenge=ZtNPunH49FD35FWYhT5Tv8I7vRKQJ8uxMaL0_9eHjNA')
            ->assertBadRequest()
            ->assertJsonPath('error', 'invalid_request');
    }

    public function test_authorization_request_with_plain_method_is_rejected(): void
    {
        $this->getJson('/_test/oauth/authorize?code_challenge=ZtNPunH49FD35FWYhT5Tv8I7vRKQJ8uxMaL0_9eHjNA&code_challenge_method=plain')
            ->assertBadRequest()
            ->assertJsonPath('error', 'invalid_request');
    }

    public function test_authorization_request_with_unsupported_method_is_rejected(): void
    {
        $this->getJson('/_test/oauth/authorize?code_challenge=ZtNPunH49FD35FWYhT5Tv8I7vRKQJ8uxMaL0_9eHjNA&code_challenge_method=unsupported')
            ->assertBadRequest()
            ->assertJsonPath('error', 'invalid_request');
    }

    public function test_authorization_request_with_s256_method_passes_middleware(): void
    {
        $this->getJson('/_test/oauth/authorize?code_challenge=ZtNPunH49FD35FWYhT5Tv8I7vRKQJ8uxMaL0_9eHjNA&code_challenge_method=S256')
            ->assertOk()
            ->assertJson([
                'message' => 'Authorization request accepted',
            ]);
    }

    public function test_authorization_approval_route_is_not_subject_to_pkce_parameter_checks(): void
    {
        $this->postJson('/_test/oauth/authorize')
            ->assertOk()
            ->assertJson([
                'message' => 'Authorization approval accepted',
            ]);
    }

    public function test_token_route_is_not_subject_to_pkce_parameter_checks(): void
    {
        $this->postJson('/_test/oauth/token')
            ->assertOk()
            ->assertJson([
                'message' => 'Token request accepted',
            ]);
    }
}
