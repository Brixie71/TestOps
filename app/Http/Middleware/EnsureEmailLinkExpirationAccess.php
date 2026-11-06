<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Authorization\OAuthScopes;
use App\Services\Authorization\PermissionChecker;
use App\Services\Authorization\ServiceClientAuthorizer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Exceptions\AuthenticationException;
use League\OAuth2\Server\Exception\OAuthServerException as LeagueOAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailLinkExpirationAccess
{
    /**
     * Application permission code that grants system roles read access.
     * Human authorization stays identical to the existing web policy.
     */
    private const VIEW_PERMISSION = 'settings.email_link_expiration.view';

    private const OPERATION_READ = 'read';

    private const OPERATION_WRITE = 'write';

    /**
     * Specify the operation parameter for the middleware.
     */
    public static function using(string $operation): string
    {
        return static::class.':'.$operation;
    }

    /**
     * Create the middleware.
     */
    public function __construct(
        private readonly ResourceServer $resourceServer,
        private readonly ServiceClientAuthorizer $serviceClientAuthorizer,
        private readonly PermissionChecker $permissionChecker,
    ) {}

    /**
     * Authorize the request principal for the requested operation.
     *
     * Machine clients (client-credentials tokens) require both the OAuth
     * scope and the provisioned application permission, and are never
     * treated as human administrators. User tokens follow the existing
     * human RBAC rules without any OAuth scope requirement.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $operation): Response
    {
        $requiredScope = $this->requiredScope($operation);

        $accessToken = $this->authenticateAccessToken($request);

        $user = $this->resolveUser($request);

        if ($user) {
            return $this->authorizeUser($request, $user, $operation, $next);
        }

        return $this->authorizeServiceClient($request, $accessToken, $requiredScope, $operation, $next);
    }

    /**
     * Resolve the active OAuth scope requirement for the operation.
     */
    private function requiredScope(string $operation): string
    {
        return match ($operation) {
            self::OPERATION_READ => OAuthScopes::SYSTEM_SETTINGS_READ,
            self::OPERATION_WRITE => OAuthScopes::SYSTEM_SETTINGS_WRITE,
            default => throw new InvalidArgumentException(
                "Unsupported email-link expiration operation [{$operation}].",
            ),
        };
    }

    /**
     * Validate the request's bearer token, rejecting absent, invalid,
     * expired, and revoked tokens.
     */
    private function authenticateAccessToken(Request $request): AccessToken
    {
        $token = $request->user()?->currentAccessToken();

        if ($token instanceof AccessToken) {
            return $token;
        }

        $psrRequest = (new PsrHttpFactory)->createRequest($request);

        try {
            $psrRequest = $this->resourceServer->validateAuthenticatedRequest($psrRequest);
        } catch (LeagueOAuthServerException) {
            throw new AuthenticationException;
        }

        return AccessToken::fromPsrRequest($psrRequest);
    }

    /**
     * Resolve the human principal for the request, if any. No machine token
     * ever resolves to a User.
     */
    private function resolveUser(Request $request): ?User
    {
        return $request->user() ?? Auth::guard('api')->user();
    }

    /**
     * Apply the existing human RBAC policy. Human tokens only need to be
     * valid and authenticated; role and permission rules govern access,
     * and no OAuth scope is required for user tokens.
     */
    private function authorizeUser(
        Request $request,
        User $user,
        string $operation,
        Closure $next
    ): Response {
        $authorized = match ($operation) {
            self::OPERATION_READ => $this->permissionChecker->allows(
                $user,
                self::VIEW_PERMISSION,
            ),
            self::OPERATION_WRITE => $this->permissionChecker->isSuperAdmin($user),
            default => false,
        };

        abort_unless($authorized, 403, 'You are not authorized to perform this action.');

        return $next($request);
    }

    /**
     * Apply the dedicated service-client authorization mechanism.
     */
    private function authorizeServiceClient(
        Request $request,
        AccessToken $accessToken,
        string $requiredScope,
        string $operation,
        Closure $next
    ): Response {
        $client = $this->serviceClientAuthorizer->activeClient($accessToken);

        if ($client === null) {
            throw new AuthenticationException;
        }

        if ($operation === self::OPERATION_WRITE) {
            abort(403, 'Machine clients cannot update system settings.');
        }

        $authorized = $this->serviceClientAuthorizer->permits(
            $client,
            $accessToken,
            $requiredScope,
            ServiceClientAuthorizer::SYSTEM_SETTINGS_READ,
        );

        abort_unless($authorized, 403, 'The service client is not authorized to perform this action.');

        return $next($request);
    }
}
