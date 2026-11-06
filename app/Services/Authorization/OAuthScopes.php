<?php

namespace App\Services\Authorization;

/**
 * Central registry of OAuth scopes and service-client permission identifiers.
 *
 * Two distinct authorization layers exist:
 *
 * 1. OAuth scopes (`system-settings:*`) restrict what an access token may do.
 *    They are token-level capability restrictions enforced by Passport.
 * 2. Application permissions (`system-settings.*`) restrict what a service
 *    client principal may do. They are stored in `service_client_permissions`
 *    and provisioned through trusted application-side code, never by the
 *    client itself.
 *
 * Machine clients must satisfy both layers; human users are ruled by the
 * system RBAC roles instead. See docs/oauth-service-authorization.md.
 */
final class OAuthScopes
{
    /**
     * OAuth scope for reading system settings.
     */
    public const SYSTEM_SETTINGS_READ = 'system-settings:read';

    /**
     * OAuth scope for updating system settings.
     */
    public const SYSTEM_SETTINGS_WRITE = 'system-settings:write';

    /**
     * All registered OAuth scopes with their consent-screen descriptions.
     * Registered through Passport::tokensCan() in AppServiceProvider.
     *
     * @return array<string, string>
     */
    public static function definitions(): array
    {
        return [
            self::SYSTEM_SETTINGS_READ => 'Read permitted system settings.',
            self::SYSTEM_SETTINGS_WRITE => 'Update permitted system settings.',
        ];
    }

    /**
     * The scopes that client-credentials (machine) tokens may receive.
     *
     * Machine clients are service principals: they must never obtain the
     * system-settings write scope, so machine-client write access stays
     * disabled regardless of what is requested or configured.
     *
     * @return string[]
     */
    public static function machineClientScopeAllowlist(): array
    {
        return [
            self::SYSTEM_SETTINGS_READ,
        ];
    }
}
