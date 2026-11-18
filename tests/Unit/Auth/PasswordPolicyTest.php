<?php

namespace Tests\Unit\Auth;

use App\Services\Auth\PasswordPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PasswordPolicyTest extends TestCase
{
    public static function passingPasswords(): array
    {
        return [
            'meets every requirement' => ['Str0ng#Passphrase!'],
            'exactly twelve characters' => ['Abcdef1#ghijk'],
        ];
    }

    public static function failingPasswords(): array
    {
        return [
            'too short' => ['Abc1#def'],
            'missing uppercase' => ['abcdef1#ghijk'],
            'missing lowercase' => ['ABCDEF1#GHIJK'],
            'missing number' => ['Abcdefgh#Ijkl'],
            'missing symbol' => ['Abcdefg1Hijkl'],
            'empty' => [''],
        ];
    }

    #[DataProvider('passingPasswords')]
    public function test_compliant_passwords_pass(string $password): void
    {
        $this->assertPolicyResult($password, true);
    }

    #[DataProvider('failingPasswords')]
    public function test_non_compliant_passwords_fail(string $password): void
    {
        $this->assertPolicyResult($password, false);
    }

    public function test_the_failure_message_states_the_full_policy(): void
    {
        $messages = [];

        (new PasswordPolicy)->validate('password', 'weak', function (string $message) use (&$messages): void {
            $messages[] = $message;
        });

        $this->assertStringContainsString('at least 12 characters', $messages[0] ?? '');
        $this->assertStringContainsString('uppercase', $messages[0] ?? '');
        $this->assertStringContainsString('lowercase', $messages[0] ?? '');
        $this->assertStringContainsString('number', $messages[0] ?? '');
        $this->assertStringContainsString('symbol', $messages[0] ?? '');
    }

    private function assertPolicyResult(string $password, bool $expectedPass): void
    {
        $messages = [];

        (new PasswordPolicy)->validate('password', $password, function (string $message) use (&$messages): void {
            $messages[] = $message;
        });

        $this->assertSame($expectedPass, $messages === [], "Password [$password] misjudged.");
    }
}
