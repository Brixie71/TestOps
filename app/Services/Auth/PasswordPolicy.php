<?php

namespace App\Services\Auth;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The approved account password policy (§11.1): minimum twelve characters
 * with at least one uppercase letter, one lowercase letter, one number, and
 * one symbol. Applied wherever a password is newly set — invitation
 * acceptance, direct account setup, administrative manual sets, and the
 * forced password-reset flow.
 */
class PasswordPolicy implements ValidationRule
{
    /**
     * Minimum accepted password length.
     */
    public const MIN_LENGTH = 12;

    /**
     * @param  Closure(string):PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = is_string($value) ? $value : '';

        if (mb_strlen($password) < self::MIN_LENGTH
            || ! preg_match('/[A-Z]/', $password)
            || ! preg_match('/[a-z]/', $password)
            || ! preg_match('/[0-9]/', $password)
            || ! preg_match('/[^A-Za-z0-9]/', $password)
        ) {
            $fail('The :attribute must be at least '.self::MIN_LENGTH.' characters and include an uppercase letter, a lowercase letter, a number, and a symbol.');
        }
    }
}
