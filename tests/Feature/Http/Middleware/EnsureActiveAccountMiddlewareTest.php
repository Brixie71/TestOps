<?php

namespace Tests\Feature\Http\Middleware;

use App\Http\Middleware\EnsureActiveAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureActiveAccountMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', EnsureActiveAccount::class])
            ->get('/_test/active-account/dashboard', fn () => 'reachable');

        // Routes that must remain accessible: auth-only and public routes
        // must not be affected by the middleware.
        Route::middleware('auth')
            ->post('/_test/active-account/logout', fn () => 'logged-out');
        Route::middleware('web')
            ->get('/_test/active-account/public', fn () => 'public');
    }

    public function test_active_accounts_can_access_protected_routes(): void
    {
        $user = User::factory()->create(['account_status' => 'active']);

        $this->actingAs($user)
            ->get('/_test/active-account/dashboard')
            ->assertOk()
            ->assertSee('reachable');
    }

    public function test_inactive_accounts_are_logged_out_and_redirected_to_login(): void
    {
        $user = User::factory()->create(['account_status' => 'invited']);

        $response = $this->actingAs($user)
            ->get('/_test/active-account/dashboard');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_soft_deleted_accounts_are_logged_out_and_redirected_to_login(): void
    {
        $user = User::factory()->create();
        $user->delete();

        $response = $this->actingAs($user)
            ->get('/_test/active-account/dashboard');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_the_middleware_logs_out_and_invalidates_the_session_for_a_trashed_user(): void
    {
        $middleware = new EnsureActiveAccount;
        $session = $this->app->make('session.store');
        $originalSessionId = $session->getId();
        $originalToken = $session->token();

        $user = User::factory()->create();
        $user->delete();
        $this->assertTrue($user->trashed());

        $request = Request::create('/_test/active-account/dashboard');
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, function (): void {
            $this->fail('The request must not reach the route for a soft-deleted user.');
        });

        $this->assertTrue($response->isRedirect());
        $this->assertStringEndsWith('/login', $response->getTargetUrl());
        $this->assertNotSame($originalSessionId, $session->getId());
        $this->assertNotSame($originalToken, $session->token());
    }

    public function test_the_middleware_logs_out_and_invalidates_the_session_for_an_inactive_user(): void
    {
        $middleware = new EnsureActiveAccount;
        $session = $this->app->make('session.store');
        $originalSessionId = $session->getId();

        $user = User::factory()->create(['account_status' => 'invited']);

        $request = Request::create('/_test/active-account/dashboard');
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, function (): void {
            $this->fail('The request must not reach the route for an inactive user.');
        });

        $this->assertTrue($response->isRedirect());
        $this->assertStringEndsWith('/login', $response->getTargetUrl());
        $this->assertNotSame($originalSessionId, $session->getId());
        $this->assertGuest();
    }

    public function test_the_middleware_passes_active_accounts_through(): void
    {
        $middleware = new EnsureActiveAccount;
        $session = $this->app->make('session.store');

        $user = User::factory()->create(['account_status' => 'active']);

        $request = Request::create('/_test/active-account/dashboard');
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $user);

        $response = $middleware->handle($request, fn () => response('protected-content'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('protected-content', $response->getContent());
    }

    public function test_middleware_does_not_interfere_with_accessible_routes(): void
    {
        $user = User::factory()->create(['account_status' => 'invited']);

        // A guest route remains publicly reachable.
        $this->get('/_test/active-account/public')->assertOk()->assertSee('public');

        // An auth-only route (like logout) stays reachable for deactivated
        // accounts that are removing their own session state.
        $this->actingAs($user)
            ->post('/_test/active-account/logout')
            ->assertOk()
            ->assertSee('logged-out');
    }
}
