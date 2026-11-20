<?php

namespace Tests\Feature\Profile;

use App\Actions\ChangeOwnPasswordAction;
use App\Mail\PasswordChangedMailable;
use App\Models\AuditLog;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_change_their_own_password(): void
    {
        Mail::fake();

        $user = User::factory()->create(['password' => 'Old-Password#1']);

        $this->actingAs($user)
            ->patch(route('profile.password.update'), [
                'current_password' => 'Old-Password#1',
                'password' => 'N3w-Password#2026',
                'password_confirmation' => 'N3w-Password#2026',
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('N3w-Password#2026', $user->refresh()->password));

        $audit = AuditLog::query()
            ->where('action', 'user.password.changed')
            ->where('target_user_id', $user->getKey())
            ->sole();

        $this->assertSame($user->getKey(), $audit->actor_user_id);
        $this->assertNull($audit->before_values);
        $this->assertSame([], $audit->after_values);

        Mail::assertSent(
            PasswordChangedMailable::class,
            fn (PasswordChangedMailable $mail): bool => $mail->hasTo($user->email),
        );

        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $user->getKey(),
            'notification_type' => 'password_changed',
            'status' => NotificationDelivery::STATUS_DELIVERED,
        ]);
    }

    public function test_the_current_password_must_be_correct(): void
    {
        Mail::fake();

        $user = User::factory()->create(['password' => 'Old-Password#1']);

        $this->actingAs($user)
            ->from(route('profile'))
            ->patch(route('profile.password.update'), [
                'current_password' => 'Wrong-Password#1',
                'password' => 'N3w-Password#2026',
                'password_confirmation' => 'N3w-Password#2026',
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('Old-Password#1', $user->refresh()->password));
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('notification_deliveries', 0);

        Mail::assertNothingSent();
    }

    public function test_the_new_password_must_satisfy_the_approved_policy(): void
    {
        Mail::fake();

        $user = User::factory()->create(['password' => 'Old-Password#1']);

        $this->actingAs($user)
            ->from(route('profile'))
            ->patch(route('profile.password.update'), [
                'current_password' => 'Old-Password#1',
                'password' => 'short1!',
                'password_confirmation' => 'short1!',
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('Old-Password#1', $user->refresh()->password));
        $this->assertDatabaseCount('audit_logs', 0);

        Mail::assertNothingSent();
    }

    public function test_the_new_password_must_be_confirmed(): void
    {
        Mail::fake();

        $user = User::factory()->create(['password' => 'Old-Password#1']);

        $this->actingAs($user)
            ->from(route('profile'))
            ->patch(route('profile.password.update'), [
                'current_password' => 'Old-Password#1',
                'password' => 'N3w-Password#2026',
                'password_confirmation' => 'Different-Password#2026',
            ])
            ->assertRedirect(route('profile'))
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('Old-Password#1', $user->refresh()->password));
        $this->assertDatabaseCount('audit_logs', 0);

        Mail::assertNothingSent();
    }

    public function test_other_sessions_are_ended_while_the_current_session_survives(): void
    {
        Mail::fake();

        $user = User::factory()->create(['password' => 'Old-Password#1']);
        $otherUser = User::factory()->create();

        $session = [
            'user_id' => $user->getKey(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ];

        DB::table(config('session.table'))->insert([
            ['id' => 'current-session-id', ...$session],
            ['id' => 'other-session-id', ...$session],
            ['id' => 'unrelated-session-id', ...$session, 'user_id' => $otherUser->getKey()],
        ]);

        $this->app->make(ChangeOwnPasswordAction::class)->execute(
            $user,
            'Old-Password#1',
            'N3w-Password#2026',
            'current-session-id',
        );

        $this->assertDatabaseHas(config('session.table'), ['id' => 'current-session-id']);
        $this->assertDatabaseMissing(config('session.table'), ['id' => 'other-session-id']);
        $this->assertDatabaseHas(config('session.table'), ['id' => 'unrelated-session-id']);
    }

    public function test_the_profile_page_renders_the_password_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee(route('profile.password.update'), false)
            ->assertSee('Change password');
    }

    public function test_guests_cannot_change_a_password(): void
    {
        $this->patch(route('profile.password.update'), [
            'current_password' => 'Old-Password#1',
            'password' => 'N3w-Password#2026',
            'password_confirmation' => 'N3w-Password#2026',
        ])->assertRedirect(route('login'));
    }

    public function test_a_deactivated_user_cannot_change_their_password(): void
    {
        $user = User::factory()->create(['password' => 'Old-Password#1']);
        $user->delete();

        $this->actingAs($user)
            ->patch(route('profile.password.update'), [
                'current_password' => 'Old-Password#1',
                'password' => 'N3w-Password#2026',
                'password_confirmation' => 'N3w-Password#2026',
            ])
            ->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('Old-Password#1', $user->refresh()->password));
    }
}
