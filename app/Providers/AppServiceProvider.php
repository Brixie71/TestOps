<?php

namespace App\Providers;

use App\Services\Authorization\OAuthScopes;
use App\Services\Authorization\ScopeRepository;
use DateInterval;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Bridge\ScopeRepository as PassportScopeRepository;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register application services.
     */
    public function register(): void
    {
        Passport::$deviceCodeGrantEnabled = false;
        Passport::$passwordGrantEnabled = false;
        Passport::$implicitGrantEnabled = false;

        // Machine-client scope policy applies at token issuance; Passport
        // resolves its scope repository through the container.
        $this->app->bind(
            PassportScopeRepository::class,
            ScopeRepository::class,
        );
    }

    /**
     * Bootstrap application services.
     */
    public function boot(): void
    {
        Passport::tokensCan(OAuthScopes::definitions());

        Passport::tokensExpireIn(new DateInterval('PT15M'));
        Passport::refreshTokensExpireIn(new DateInterval('P7D'));
        Passport::clientCredentialsTokensExpireIn(new DateInterval('PT15M'));
    }
}
