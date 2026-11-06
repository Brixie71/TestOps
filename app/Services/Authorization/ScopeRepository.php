<?php

namespace App\Services\Authorization;

use Laravel\Passport\Bridge\ScopeRepository as PassportScopeRepository;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;

class ScopeRepository extends PassportScopeRepository
{
    /**
     * Finalize the granted scopes, enforcing the machine-client scope policy.
     *
     * Passport validates requested scopes against the registered scope
     * definitions only; globally registered scopes are not a per-client
     * allowlist. Token issuance applies this allowlist so client-credentials
     * (machine) tokens can never receive beyond their approved scope set —
     * system-settings write access stays disabled for machine clients.
     *
     * Should be bound to Laravel\Passport\Bridge\ScopeRepository in
     * AppServiceProvider; Passport's authorization server resolves the
     * repository through the container.
     */
    public function finalizeScopes(
        array $scopes,
        string $grantType,
        ClientEntityInterface $clientEntity,
        ?string $userIdentifier = null,
        ?string $authCodeId = null
    ): array {
        $finalized = parent::finalizeScopes(
            $scopes,
            $grantType,
            $clientEntity,
            $userIdentifier,
            $authCodeId,
        );

        if ($grantType !== 'client_credentials') {
            return $finalized;
        }

        $allowlist = OAuthScopes::machineClientScopeAllowlist();

        return collect($finalized)
            ->filter(
                fn (ScopeEntityInterface $scope): bool => in_array(
                    $scope->getIdentifier(),
                    $allowlist,
                    true,
                ),
            )
            ->values()
            ->all();
    }
}
