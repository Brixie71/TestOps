<?php

namespace App\Services\Auth;

use RuntimeException;

/**
 * Rejected forced password-reset requests (§11.3).
 *
 * Messages are user-safe: they never contain credentials, tokens, or
 * account state beyond what the recipient needs to act next.
 */
class PasswordResetException extends RuntimeException
{
    public static function notFound(): self
    {
        return new self('This password reset link is no longer valid.');
    }

    public static function consumed(): self
    {
        return new self('This password reset link has already been used.');
    }

    public static function expired(): self
    {
        return new self('This password reset link has expired. Please request a fresh link.');
    }

    public static function invalidated(): self
    {
        return new self('This password reset link is no longer valid.');
    }

    public static function attemptLimitReached(): self
    {
        return new self('Too many failed attempts. This password reset link has been disabled.');
    }

    public static function tooSoon(): self
    {
        return new self('A password reset link was just issued for this account. Please wait a minute before issuing another one.');
    }

    public static function tooManyIssued(): self
    {
        return new self('Too many password reset links were issued for this account within the last hour.');
    }
}
