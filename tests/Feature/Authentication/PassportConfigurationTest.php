<?php

namespace Tests\Feature\Authentication;

use App\Services\Authorization\OAuthScopes;
use Laravel\Passport\Passport;
use Tests\TestCase;

class PassportConfigurationTest extends TestCase
{
    public function test_device_authorization_grant_is_disabled(): void
    {
        $this->assertFalse(Passport::$deviceCodeGrantEnabled);
    }

    public function test_password_grant_is_disabled(): void
    {
        $this->assertFalse(Passport::$passwordGrantEnabled);
    }

    public function test_implicit_grant_is_disabled(): void
    {
        $this->assertFalse(Passport::$implicitGrantEnabled);
    }

    public function test_access_token_lifetime_is_fifteen_minutes(): void
    {
        $lifetime = Passport::tokensExpireIn();

        $this->assertSame(0, $lifetime->d);
        $this->assertSame(0, $lifetime->h);
        $this->assertSame(15, $lifetime->i);
    }

    public function test_refresh_token_lifetime_is_seven_days(): void
    {
        $lifetime = Passport::refreshTokensExpireIn();

        $this->assertSame(7, $lifetime->d);
        $this->assertSame(0, $lifetime->h);
        $this->assertSame(0, $lifetime->i);
    }

    public function test_client_credentials_token_lifetime_is_fifteen_minutes(): void
    {
        $lifetime = Passport::clientCredentialsTokensExpireIn();

        $this->assertSame(0, $lifetime->d);
        $this->assertSame(0, $lifetime->h);
        $this->assertSame(15, $lifetime->i);
    }

    public function test_system_settings_scopes_are_registered(): void
    {
        $scopes = Passport::scopesFor([
            'system-settings:read',
            'system-settings:write',
        ]);

        $this->assertCount(2, $scopes);

        $this->assertSame(
            OAuthScopes::SYSTEM_SETTINGS_READ,
            $scopes[0]->id,
        );
        $this->assertSame(
            OAuthScopes::definitions()[OAuthScopes::SYSTEM_SETTINGS_READ],
            $scopes[0]->description,
        );

        $this->assertSame(
            OAuthScopes::SYSTEM_SETTINGS_WRITE,
            $scopes[1]->id,
        );
        $this->assertSame(
            OAuthScopes::definitions()[OAuthScopes::SYSTEM_SETTINGS_WRITE],
            $scopes[1]->description,
        );
    }
}
