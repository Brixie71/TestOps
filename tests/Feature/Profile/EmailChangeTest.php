<?php

namespace Tests\Feature\Profile;

use App\Mail\EmailChangeVerificationMailable;
use App\Models\EmailVerificationRequest;
use App\Models\User;
use App\Models\UserEmailAddress;
use App\Services\Auth\EmailVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailChangeTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Current#Password1';

    private function user(array $attributes = []): User
    {
        return User::factory()->create([
            'password' => self::PASSWORD,
            ...$attributes,
        ]);
    }

    public function test_the_profile_shows_the_current_email(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee($user->email)
            ->assertDontSee('awaiting verification');
    }

    public function test_the_profile_shows_a_pending_change_after_a_request(): void
    {
        Mail::fake();

        $user = $this->user();

        $this->actingAs($user)->post(route('profile.email.update'), [
            'email' => 'pending-view@example.com',
            'current_password' => self::PASSWORD,
        ]);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('pending-view@example.com')
            ->assertSee('awaiting')
            ->assertSee(route('profile.email.cancel'), false);
    }

    public function test_a_user_can_request_a_change_with_the_current_password(): void
    {
        Mail::fake();

        $user = $this->user();

        $this->actingAs($user)
            ->from(route('profile'))
            ->post(route('profile.email.update'), [
                'email' => 'new-address@example.com',
                'current_password' => self::PASSWORD,
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('user_email_addresses', [
            'user_id' => $user->getKey(),
            'email' => 'new-address@example.com',
            'status' => UserEmailAddress::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('email_verification_requests', [
            'user_id' => $user->getKey(),
            'purpose' => EmailVerificationService::PURPOSE_EMAIL_CHANGE,
            'email' => 'new-address@example.com',
            'state' => EmailVerificationRequest::STATE_ISSUED,
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $user->getKey(),
            'recipient_email' => 'new-address@example.com',
            'notification_type' => 'email_change_verification',
            'status' => 'delivered',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.email.change_requested',
            'target_user_id' => $user->getKey(),
        ]);

        Mail::assertSent(
            EmailChangeVerificationMailable::class,
            fn (EmailChangeVerificationMailable $mail): bool => $mail->hasTo('new-address@example.com'),
        );

        // The current address stays active until verification.
        $this->assertSame($user->email, $user->refresh()->email);
        $this->assertDatabaseHas('user_email_addresses', [
            'user_id' => $user->getKey(),
            'email' => $user->email,
            'status' => UserEmailAddress::STATUS_CURRENT,
        ]);
    }

    public function test_a_wrong_current_password_is_rejected(): void
    {
        Mail::fake();

        $user = $this->user();

        $this->actingAs($user)
            ->from(route('profile'))
            ->post(route('profile.email.update'), [
                'email' => 'new-address@example.com',
                'current_password' => 'not-my-password',
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHasErrors('current_password');

        $this->assertDatabaseMissing('user_email_addresses', [
            'email' => 'new-address@example.com',
        ]);
        $this->assertDatabaseCount('email_verification_requests', 0);
    }

    public function test_the_current_email_cannot_be_requested_again(): void
    {
        Mail::fake();

        $user = $this->user();

        $this->actingAs($user)
            ->from(route('profile'))
            ->post(route('profile.email.update'), [
                'email' => $user->email,
                'current_password' => self::PASSWORD,
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHasErrors('email');
    }

    public function test_an_address_reserved_by_another_account_is_rejected(): void
    {
        Mail::fake();

        $actor = $this->user();
        $other = $this->user();
        $other->emailAddresses()->create([
            'email' => 'reserved-elsewhere@example.com',
            'status' => UserEmailAddress::STATUS_PREVIOUS,
            'verified_at' => now()->subYear(),
        ]);

        $this->actingAs($actor)
            ->from(route('profile'))
            ->post(route('profile.email.update'), [
                'email' => 'reserved-elsewhere@example.com',
                'current_password' => self::PASSWORD,
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHasErrors('email');
    }

    public function test_a_second_request_replaces_the_pending_address_and_token(): void
    {
        Mail::fake();

        $user = $this->user();

        $this->actingAs($user)->post(route('profile.email.update'), [
            'email' => 'first@example.com',
            'current_password' => self::PASSWORD,
        ]);

        $firstToken = EmailVerificationRequest::query()->sole();

        // §10.3 enforces a 60-second resend cooldown between replacements.
        $this->travel(61)->seconds();

        $this->actingAs($user)->post(route('profile.email.update'), [
            'email' => 'second@example.com',
            'current_password' => self::PASSWORD,
        ]);

        $this->assertDatabaseMissing('user_email_addresses', [
            'email' => 'first@example.com',
        ]);

        $this->assertDatabaseHas('user_email_addresses', [
            'user_id' => $user->getKey(),
            'email' => 'second@example.com',
            'status' => UserEmailAddress::STATUS_PENDING,
        ]);

        // §9.5: the previous token is invalidated, exactly one stays issued.
        $this->assertDatabaseHas('email_verification_requests', [
            'id' => $firstToken->id,
            'state' => EmailVerificationRequest::STATE_INVALIDATED,
        ]);

        $this->assertSame(1, EmailVerificationRequest::query()
            ->where('purpose', EmailVerificationService::PURPOSE_EMAIL_CHANGE)
            ->where('state', EmailVerificationRequest::STATE_ISSUED)
            ->count());
    }

    public function test_the_new_address_must_be_available_again_after_a_cancelled_request(): void
    {
        Mail::fake();

        $user = $this->user();

        $this->actingAs($user)->post(route('profile.email.update'), [
            'email' => 'released@example.com',
            'current_password' => self::PASSWORD,
        ]);

        $this->actingAs($user)->post(route('profile.email.cancel'), [
            'current_password' => self::PASSWORD,
        ]);

        $this->assertDatabaseMissing('user_email_addresses', [
            'email' => 'released@example.com',
        ]);
    }

    public function test_a_user_can_cancel_their_own_pending_change(): void
    {
        Mail::fake();

        $user = $this->user();

        $this->actingAs($user)->post(route('profile.email.update'), [
            'email' => 'pending@example.com',
            'current_password' => self::PASSWORD,
        ]);

        $this->actingAs($user)
            ->from(route('profile'))
            ->post(route('profile.email.cancel'), [
                'current_password' => self::PASSWORD,
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('user_email_addresses', [
            'user_id' => $user->getKey(),
            'status' => UserEmailAddress::STATUS_PENDING,
        ]);

        $this->assertSame(0, EmailVerificationRequest::query()
            ->where('state', EmailVerificationRequest::STATE_ISSUED)
            ->count());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.email.change_cancelled',
            'target_user_id' => $user->getKey(),
        ]);

        // The current verified address is untouched by a cancellation.
        $this->assertSame($user->email, $user->refresh()->email);
    }

    public function test_cancelling_requires_the_current_password(): void
    {
        Mail::fake();

        $user = $this->user();

        $this->actingAs($user)->post(route('profile.email.update'), [
            'email' => 'pending@example.com',
            'current_password' => self::PASSWORD,
        ]);

        $this->actingAs($user)
            ->from(route('profile'))
            ->post(route('profile.email.cancel'), [
                'current_password' => 'wrong-password',
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHasErrors('current_password');

        $this->assertDatabaseHas('user_email_addresses', [
            'user_id' => $user->getKey(),
            'status' => UserEmailAddress::STATUS_PENDING,
        ]);
    }

    public function test_cancelling_without_a_pending_change_is_rejected(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->from(route('profile'))
            ->post(route('profile.email.cancel'), [
                'current_password' => self::PASSWORD,
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHasErrors('email');
    }
}
