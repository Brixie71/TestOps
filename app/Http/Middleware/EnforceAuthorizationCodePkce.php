<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\Exceptions\OAuthServerException as PassportOAuthServerException;
use League\OAuth2\Server\Exception\OAuthServerException as LeagueOAuthServerException;
use Symfony\Component\HttpFoundation\Response;

class EnforceAuthorizationCodePkce
{
    /**
     * Enforce PKCE with S256 for every authorization-code request.
     *
     * @throws PassportOAuthServerException
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->route()?->getName() !== 'passport.authorizations.authorize'
            || ! $request->isMethod('GET')
        ) {
            return $next($request);
        }

        $codeChallenge = $request->query('code_challenge');
        $codeChallengeMethod = $request->query('code_challenge_method');

        if (! is_string($codeChallenge) || $codeChallenge === '') {
            throw new PassportOAuthServerException(
                LeagueOAuthServerException::invalidRequest(
                    'code_challenge',
                    'PKCE is required for all authorization-code clients.'
                )
            );
        }

        if (! is_string($codeChallengeMethod) || $codeChallengeMethod !== 'S256') {
            throw new PassportOAuthServerException(
                LeagueOAuthServerException::invalidRequest(
                    'code_challenge_method',
                    'Only the S256 PKCE challenge method is supported.'
                )
            );
        }

        return $next($request);
    }
}
