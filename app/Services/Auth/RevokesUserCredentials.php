<?php

namespace App\Services\Auth;

use Laravel\Passport\Passport;

/**
 * Revokes every persistent OAuth credential belonging to a single user.
 *
 * Covers active access tokens, their linked refresh tokens, and outstanding
 * authorization codes. Client-credentials tokens are machine-client
 * credentials and are intentionally left untouched here.
 */
class RevokesUserCredentials
{
    /**
     * Revoke all active (non-revoked) OAuth credentials belonging to the
     * given user and return their counts only.
     *
     * Refresh tokens are selected through the user's collected access-token
     * ids, including already-revoked access tokens: an earlier revocation
     * can leave refresh tokens linked to those access tokens still active.
     * All revoked-state changes are idempotent bulk updates.
     *
     * The service never starts, commits, or rolls back a transaction and
     * never writes audit events; the caller owns both.
     *
     * @return array{access_tokens: int, refresh_tokens: int, authorization_codes: int}
     */
    public function revokeForUser(int|string $userId): array
    {
        $accessTokenIds = Passport::token()->newQuery()
            ->where('user_id', $userId)
            ->pluck('id');

        $accessTokens = Passport::token()->newQuery()
            ->where('user_id', $userId)
            ->where('revoked', false)
            ->update(['revoked' => true]);

        $refreshTokens = Passport::refreshToken()->newQuery()
            ->whereIn('access_token_id', $accessTokenIds)
            ->where('revoked', false)
            ->update(['revoked' => true]);

        $authorizationCodes = Passport::authCode()->newQuery()
            ->where('user_id', $userId)
            ->where('revoked', false)
            ->update(['revoked' => true]);

        return [
            'access_tokens' => $accessTokens,
            'refresh_tokens' => $refreshTokens,
            'authorization_codes' => $authorizationCodes,
        ];
    }
}
