<?php

namespace Tests\Feature\Auth;

use App\Actions\AcceptInvitationAction;
use App\Actions\CancelUserInvitationAction;
use App\Actions\IssueUserInvitationAction;
use App\Actions\RecoverUserInvitationAction;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\Auth\InvitationLinkException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Mail;
use Tests\TestCase;

class InvitationReissueTest extends TestCase
{
    use RefreshDatabase;

    private function issue(User $admin, string $email): array
    {
        return app(IssueUserInvitationAction::class)->execute($admin, $email);
    }

    private function reissue(User $admin, string $email): array
    {
        return app(IssueUserInvitationAction::class)->execute($admin, $email);
    }

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_a_cancelled_invitation_can_be_reinvited_with_fresh_credentials(): void
    {
        Mail::fake();

        $admin = $this->admin();

        $issued = $this->issue($admin, 'reissue.me@example.net');

        app(CancelUserInvitationAction::class)->execute($admin, $issued['invitation'], 'Wrong person');

        $reissued = $this->reissue($admin, 'reissue.me@example.net');

        // The new lifecycle is a brand-new invitation row with fresh
        // credentials at generation 1.
        $newInvitation = $reissued['invitation'];

        $this->assertNotSame($issued['invitation']->getKey(), $newInvitation->getKey());
        $this->assertSame('reissue.me@example.net', $newInvitation->invited_email);
        $this->assertSame(1, (int) $newInvitation->credential_generation);
        $this->assertSame('pending', $newInvitation->state());
        $this->assertNull($newInvitation->revoked_at);
        $this->assertNotSame($issued['token'], $reissued['token']);
        $this->assertNotSame(
            $issued['invitation']->token_hash,
            $newInvitation->token_hash,
        );
        $this->assertNotSame(
            $issued['invitation']->recovery_code_hash,
            $newInvitation->recovery_code_hash,
        );
        $this->assertSame(2, UserInvitation::query()->count());
    }

    public function test_the_cancelled_invitation_history_is_untouched(): void
    {
        Mail::fake();

        $admin = $this->admin();

        $issued = $this->issue($admin, 'history@example.net');

        app(CancelUserInvitationAction::class)->execute($admin, $issued['invitation'], 'No longer wanted');

        $cancelled = $issued['invitation']->refresh()->toArray();

        $this->reissue($admin, 'history@example.net');

        $afterReissue = $issued['invitation']->refresh()->toArray();

        // Every historical attribute of the cancelled invitation survives
        // the re-issue unchanged.
        $this->assertSame($cancelled, $afterReissue);

        $this->assertNotNull($afterReissue['revoked_at']);
        $this->assertSame('No longer wanted', $afterReissue['cancelled_reason']);
        $this->assertNull($afterReissue['accepted_at']);

        // The original ledger row was reused, not duplicated.
        $this->assertSame(1, $issued['invitation']->user()->count());
    }

    public function test_credentials_of_the_cancelled_invitation_cannot_accept_or_recover(): void
    {
        Mail::fake();

        $admin = $this->admin();

        $issued = $this->issue($admin, 'dead.credentials@example.net');

        app(CancelUserInvitationAction::class)->execute($admin, $issued['invitation'], 'Mistake');

        $reissued = $this->reissue($admin, 'dead.credentials@example.net');

        // The old link still resolves to the cancelled invitation and is
        // rejected there — it can never accept the new lifecycle.
        try {
            app(AcceptInvitationAction::class)->execute($issued['token'], 'Accept-Password-1');

            $this->fail('A cancelled invitation link must stay rejected after a re-issue.');
        } catch (InvitationLinkException $exception) {
            $this->assertSame(
                InvitationLinkException::REASON_CANCELLED,
                $exception->reason,
            );
        }

        // The old recovery code no longer matches any invitation of the
        // account: recovery looks at the newest lifecycle only.
        $this->travel(2)->minutes();

        try {
            app(RecoverUserInvitationAction::class)->execute(
                'dead.credentials@example.net',
                $issued['recoveryCode'],
                '203.0.113.70',
            );

            $this->fail('The cancelled invitation recovery code must not recover anything.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'Recovery could not be completed',
                $exception->getMessage(),
            );
        }

        // The failed attempt is counted on the new lifecycle, which stays
        // pending and untouched otherwise.
        $this->assertSame(1, (int) $reissued['invitation']->refresh()->recovery_attempts);
        $this->assertSame('pending', $reissued['invitation']->state());
    }

    public function test_the_new_invitation_can_be_accepted_normally(): void
    {
        Mail::fake();

        $admin = $this->admin();

        $issued = $this->issue($admin, 'fresh.start@example.net');

        app(CancelUserInvitationAction::class)->execute($admin, $issued['invitation'], 'Changed plan');

        $reissued = $this->reissue($admin, 'fresh.start@example.net');

        $invitedUser = User::query()->where('email', 'fresh.start@example.net')->firstOrFail();

        app(AcceptInvitationAction::class)->execute($reissued['token'], 'Accept-Password-1');

        $this->assertTrue(Hash::check('Accept-Password-1', $invitedUser->refresh()->password));
        $this->assertNotNull($reissued['invitation']->refresh()->accepted_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'invitation.accepted',
        ]);
    }

    public function test_a_second_reissue_while_the_new_invitation_is_live_is_rejected(): void
    {
        Mail::fake();

        $admin = $this->admin();

        $issued = $this->issue($admin, 'dupe.pending@example.net');

        app(CancelUserInvitationAction::class)->execute($admin, $issued['invitation'], 'First cancel');

        $this->reissue($admin, 'dupe.pending@example.net');

        try {
            $this->reissue($admin, 'dupe.pending@example.net');

            $this->fail('A live pending invitation must prevent another invitation.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'already registered',
                $exception->getMessage(),
            );
        }

        $this->assertSame(2, UserInvitation::query()->count());
        $this->assertSame(
            1,
            UserInvitation::query()->whereNull('revoked_at')->whereNull('accepted_at')->count(),
        );
    }

    public function test_a_pending_invitation_cannot_be_reinvited(): void
    {
        Mail::fake();

        $admin = $this->admin();

        $this->issue($admin, 'still.live@example.net');

        try {
            $this->reissue($admin, 'still.live@example.net');

            $this->fail('A pending invitation blocks a new invitation.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('already registered', $exception->getMessage());
        }

        $this->assertSame(1, UserInvitation::query()->count());
    }

    public function test_an_active_account_cannot_be_invited_again(): void
    {
        Mail::fake();

        $admin = $this->admin();

        User::factory()->create(['email' => 'active.acct@example.net']);

        try {
            $this->reissue($admin, 'active.acct@example.net');

            $this->fail('An active account blocks new invitations.');
        } catch (ValidationException) {
        }

        $this->assertSame(1, User::withTrashed()->where('email', 'active.acct@example.net')->count());
    }

    public function test_a_deactivated_account_cannot_be_invited_again(): void
    {
        Mail::fake();

        $admin = $this->admin();

        $deactivated = User::factory()->create(['email' => 'deactivated.acct@example.net']);
        $deactivated->delete();

        try {
            $this->reissue($admin, 'deactivated.acct@example.net');

            $this->fail('A deactivated account blocks new invitations.');
        } catch (ValidationException) {
        }

        $this->assertSame(0, UserInvitation::query()->count());
    }

    public function test_reissued_invitations_can_repeat_lifecycle_after_later_cancellations(): void
    {
        Mail::fake();

        $admin = $this->admin();

        $first = $this->issue($admin, 'repeat.cycle@example.net');

        app(CancelUserInvitationAction::class)->execute($admin, $first['invitation'], 'Cancel 1');

        $second = $this->reissue($admin, 'repeat.cycle@example.net');

        app(CancelUserInvitationAction::class)->execute($admin, $second['invitation'], 'Cancel 2');

        $third = $this->reissue($admin, 'repeat.cycle@example.net');

        $this->assertSame(3, UserInvitation::query()->count());
        $this->assertSame(3, UserInvitation::query()->where('user_id', $third['invitation']->user_id)->count());
        $this->assertSame('pending', $third['invitation']->state());
    }

    public function test_reissue_never_recreates_the_account_or_duplicates_the_creation_audit(): void
    {
        Mail::fake();

        $admin = $this->admin();

        $issued = $this->issue($admin, 'audit.once@example.net');

        app(CancelUserInvitationAction::class)->execute($admin, $issued['invitation'], 'Cancel');

        $this->reissue($admin, 'audit.once@example.net');

        $this->assertSame(1, User::withTrashed()->where('email', 'audit.once@example.net')->count());

        $this->assertSame(
            1,
            AuditLog::query()->where('action', 'user.account_created')->count(),
        );

        $this->assertSame(
            2,
            AuditLog::query()->where('action', 'invitation.issued')->count(),
        );
    }

    public function test_two_issuances_for_the_same_email_never_create_two_live_invitations(): void
    {
        Mail::fake();

        $admin = $this->admin();

        $this->issue($admin, 'race@example.net');

        // Sequential double-issuance: the second call runs against the
        // row-locked eligibility state and is rejected. True parallel
        // issuance needs the PostgreSQL verification run (SQLite
        // serializes writes in tests).
        app(CancelUserInvitationAction::class)->execute(
            $admin,
            UserInvitation::query()->where('invited_email', 'race@example.net')->sole(),
            'Cancel',
        );

        $this->reissue($admin, 'race@example.net');

        try {
            $this->reissue($admin, 'race@example.net');

            $this->fail('A concurrent-looking second issuance must be rejected.');
        } catch (ValidationException) {
        }

        $this->assertSame(
            1,
            UserInvitation::query()->where('invited_email', 'race@example.net')->whereNull('revoked_at')->count(),
        );
    }

    public function test_an_admin_without_the_users_create_permission_cannot_reinvite(): void
    {
        Mail::fake();

        $issuer = $this->admin();

        $issued = $this->issue($issuer, 'no.permission@example.net');

        app(CancelUserInvitationAction::class)->execute($issuer, $issued['invitation'], 'Cancel');

        $role = Role::query()->firstOrCreate(
            ['code' => 'SYSTEM_ADMIN', 'scope' => 'system'],
            ['name' => 'SYSTEM_ADMIN'],
        );

        $unauthorizedAdmin = User::factory()->create(['role_id' => $role->id]);

        $this->actingAs($unauthorizedAdmin)
            ->post('/settings/users/invitations', [
                'email' => 'no.permission@example.net',
                'method' => 'invitation_link',
            ])
            ->assertForbidden();

        // Nothing was issued; the cancelled invitation remains the newest.
        $this->assertSame(1, UserInvitation::query()->where('invited_email', 'no.permission@example.net')->count());
    }
}
