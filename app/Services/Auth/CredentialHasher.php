<?php

namespace App\Services\Auth;

/**
 * Credential hashing for invitation and verification tokens and recovery
 * codes.
 *
 * Values are hashed with HMAC-SHA256 keyed by the application key, so the
 * database never stores a raw credential and tokens in different domains
 * do not collide with each other's hashes.
 */
class CredentialHasher
{
    /**
     * Hash one credential for storage.
     */
    public function hash(string $credential): string
    {
        return bin2hex(hash_hmac('sha256', $credential, (string) config('app.key'), true));
    }

    /**
     * Constant-time comparison between a raw credential and a stored hash.
     */
    public function matches(string $credential, string $storedHash): bool
    {
        return hash_equals((string) $storedHash, $this->hash($credential));
    }
}
