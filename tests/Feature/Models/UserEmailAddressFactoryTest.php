<?php

namespace Tests\Feature\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserEmailAddressFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_a_current_email_address_for_a_verified_user(): void
    {
        $user = User::factory()->create();

        $this->assertSame(1, $user->emailAddresses()->count());

        $emailAddress = $user->emailAddresses()->firstOrFail();

        $this->assertSame($user->email, $emailAddress->email);
        $this->assertSame('current', $emailAddress->status);
        $this->assertEquals(
            $user->email_verified_at,
            $emailAddress->verified_at
        );
    }

    public function test_factory_creates_an_unverified_current_email_address_for_an_unverified_user(): void
    {
        $user = User::factory()->unverified()->create();

        $this->assertSame(1, $user->emailAddresses()->count());

        $emailAddress = $user->emailAddresses()->firstOrFail();

        $this->assertSame($user->email, $emailAddress->email);
        $this->assertSame('current', $emailAddress->status);
        $this->assertNull($user->email_verified_at);
        $this->assertNull($emailAddress->verified_at);
    }

    public function test_factory_creates_an_active_account_by_default(): void
    {
        $user = User::factory()->create();

        $this->assertSame('active', $user->account_status);
    }

    public function test_factory_can_create_an_invited_account(): void
    {
        $user = User::factory()->create([
            'account_status' => 'invited',
        ]);

        $this->assertSame('invited', $user->account_status);
    }
}
