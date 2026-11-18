<?php

namespace App\Services\Auth;

use Illuminate\Support\Str;

/**
 * Generates raw invitation credentials: URL-safe link tokens and recovery
 * codes. Raw values are handed to the recipient once; only the Credential
 * Hasher output is ever persisted.
 */
class InvitationCredentialGenerator
{
    /**
     * Recovery code format: six digits.
     */
    public const RECOVERY_FORMAT_NUMERIC_6 = 'numeric_6';

    /**
     * Recovery code format: nine alphanumeric characters.
     */
    public const RECOVERY_FORMAT_ALPHANUMERIC_9 = 'alphanumeric_9';

    /**
     * Generate a fresh link token (64 URL-safe hexadecimal characters).
     */
    public function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Generate a fresh recovery code in the requested format.
     */
    public function generateRecoveryCode(
        string $format = self::RECOVERY_FORMAT_NUMERIC_6
    ): string {
        return match ($format) {
            self::RECOVERY_FORMAT_NUMERIC_6 => $this->randomNumeric(6),
            self::RECOVERY_FORMAT_ALPHANUMERIC_9 => Str::upper(Str::random(9)),
            default => throw new \InvalidArgumentException(
                "Unsupported recovery code format [{$format}].",
            ),
        };
    }

    private function randomNumeric(int $length): string
    {
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= (string) random_int(0, 9);
        }

        return $code;
    }
}
