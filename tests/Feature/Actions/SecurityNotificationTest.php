<?php

namespace Tests\Feature\Actions;

use App\Actions\AssignUserRoleAction;
use App\Actions\DeactivateUserAction;
use App\Actions\ReactivateUserAction;
use App\Mail\AccountDeactivatedMailable;
use App\Mail\AccountReactivatedMailable;
use App\Mail\SystemRoleChangedMailable;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class SecurityNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_deactivation_notifies_the_account_holder_and_records_delivery(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();

        app(DeactivateUserAction::class)->execute($actor, $target, 'Policy violation.');

        Mail::assertSent(
            AccountDeactivatedMailable::class,
            fn (AccountDeactivatedMailable $mail): bool => $mail->hasTo($target->email),
        );

        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $target->getKey(),
            'recipient_email' => $target->email,
            'notification_type' => 'account_deactivated',
            'status' => 'delivered',
        ]);
    }

    public function test_reactivation_notifies_the_account_holder_and_records_delivery(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();
        $target->delete();

        app(ReactivateUserAction::class)->execute($actor, $target);

        Mail::assertSent(
            AccountReactivatedMailable::class,
            fn (AccountReactivatedMailable $mail): bool => $mail->hasTo($target->email),
        );

        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $target->getKey(),
            'recipient_email' => $target->email,
            'notification_type' => 'account_reactivated',
            'status' => 'delivered',
        ]);
    }

    public function test_a_role_change_notifies_the_account_holder_with_the_role_names(): void
    {
        $actor = $this->createSuperAdmin();

        $previousRole = $this->createSystemRole('ANALYST', 'Analyst');
        $newRole = $this->createSystemRole('LEAD', 'Lead');

        $target = User::factory()->create(['role_id' => $previousRole->id]);

        app(AssignUserRoleAction::class)->execute($actor, $target, $newRole->id);

        Mail::assertSent(
            SystemRoleChangedMailable::class,
            fn (SystemRoleChangedMailable $mail): bool => $mail->hasTo($target->email)
                && $mail->previousRoleName === 'Analyst'
                && $mail->newRoleName === 'Lead',
        );

        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $target->getKey(),
            'notification_type' => 'system_role_changed',
            'status' => 'delivered',
        ]);
    }

    public function test_a_failed_notification_does_not_roll_back_the_deactivation(): void
    {
        $actor = $this->createSuperAdmin();
        $target = User::factory()->create();

        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new RuntimeException('Mail transport is unavailable.'));

        app(DeactivateUserAction::class)->execute($actor, $target, 'Policy violation.');

        $this->assertSoftDeleted('users', ['id' => $target->getKey()]);

        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $target->getKey(),
            'notification_type' => 'account_deactivated',
            'status' => 'failed',
            'failure_code' => 'delivery_failed',
        ]);
    }

    private function createSuperAdmin(): User
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            ['name' => 'Super Admin', 'scope' => 'system'],
        );

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function createSystemRole(string $code, string $name): Role
    {
        return Role::query()->firstOrCreate(
            ['code' => $code],
            ['name' => $name, 'scope' => 'system'],
        );
    }
}
