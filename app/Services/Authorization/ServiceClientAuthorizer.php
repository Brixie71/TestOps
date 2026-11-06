<?php

namespace App\Services\Authorization;

use App\Models\ServiceClientPermission;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

/**
 * Dedicated authorization for machine (service) clients.
 *
 * A service client is a service principal authenticated through Passport's
 * client-credentials grant — never a human User and never a holder of
 * SUPER_ADMIN or SYSTEM_ADMIN rights. Access requires ALL checks:
 *
 * 1. The OAuth client is configured for the Client Credentials grant.
 * 2. The validated OAuth access token carries the required scope
 *    (token-level capability restriction).
 * 3. The OAuth client was provisioned with the required application-level
 *    permission through trusted application-side code, never by clients
 *    themselves (`service_client_permissions` table).
 *
 * Revoked clients and revoked access tokens fail through fresh active-client
 * lookup and resource-server token validation.
 */
class ServiceClientAuthorizer
{
    /**
     * Application-level permission for reading system settings. The only
     * permission assigned to machine clients in this release; no write
     * permission is provisioned.
     */
    public const SYSTEM_SETTINGS_READ = 'system-settings.read';

    /**
     * The active (non-revoked) OAuth client the token was issued to.
     *
     * The lookup runs against the OAuth client table without relying on
     * `ClientRepository::find*()` memoization, which can serve a cached
     * client snapshot within a single process lifetime.
     */
    public function activeClient(AccessToken $token): ?Client
    {
        $clientId = $token->oauth_client_id;

        if (! is_string($clientId) || $clientId === '') {
            return null;
        }

        return Passport::client()->newQuery()
            ->where('revoked', false)
            ->find($clientId);
    }

    /**
     * Determine whether the token represents a machine principal issued
     * through the client-credentials grant.
     *
     * User-bound access tokens violate this rule: tokens may only identify a
     * client when the token belongs to that client itself.
     */
    public function isMachineClientToken(AccessToken $token): bool
    {
        $userId = $token->oauth_user_id;

        return empty($userId) || $userId === $token->oauth_client_id;
    }

    /**
     * Determine whether a service client may perform an operation: the client
     * must be configured for the Client Credentials grant, the token must
     * carry the OAuth scope, and the client must hold the application-level
     * permission.
     */
    public function permits(
        Client $client,
        AccessToken $token,
        string $requiredScope,
        string $requiredPermission
    ): bool {
        if ($token->oauth_client_id !== (string) $client->getKey()) {
            return false;
        }

        if (! $client->hasGrantType('client_credentials')) {
            return false;
        }

        if (! $this->isMachineClientToken($token)) {
            return false;
        }

        if ($token->cant($requiredScope)) {
            return false;
        }

        return ServiceClientPermission::granted($client->getKey(), $requiredPermission);
    }
}
