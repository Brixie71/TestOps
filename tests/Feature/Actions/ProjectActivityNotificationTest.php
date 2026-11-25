<?php

namespace Tests\Feature\Actions;

use App\Actions\AddProjectMemberAction;
use App\Actions\AssignProjectOwnerAction;
use App\Actions\ChangeProjectMemberRoleAction;
use App\Actions\RemoveProjectMemberAction;
use App\Actions\TransferProjectOwnerAction;
use App\Actions\UnassignProjectOwnerAction;
use App\Mail\ProjectActivityMailable;
use App\Models\NotificationDelivery;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\Notifications\ProjectActivityMessageFactory;
use App\Services\Notifications\ProjectActivityRetryService;
use Database\Seeders\ProjectAuthorizationPermissionSeeder;
use Database\Seeders\ProjectRoleSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProjectActivityNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const REASON = 'Project activity notification coverage';

    protected function setUp(): void
    {
        parent::setUp();

        (new RoleSeeder)->run();
        (new ProjectRoleSeeder)->run();
        (new ProjectAuthorizationPermissionSeeder)->run();

        Mail::fake();
    }

    public function test_member_addition_notifies_member_and_owner_but_never_the_actor(): void
    {
        $project = Project::factory()->create();
        $owner = $project->owner;
        $actor = $this->systemUser('SUPER_ADMIN');
        $member = User::factory()->create();

        $this->app->make(AddProjectMemberAction::class)
            ->execute($actor, $project, $member, $this->projectRole('PROJECT_VIEWER')->id, self::REASON);

        Mail::assertSent(ProjectActivityMailable::class, fn (ProjectActivityMailable $mail): bool => $mail->hasTo($member->email));
        Mail::assertSent(ProjectActivityMailable::class, fn (ProjectActivityMailable $mail): bool => $mail->hasTo($owner->email));
        Mail::assertNotSent(ProjectActivityMailable::class, fn (ProjectActivityMailable $mail): bool => $mail->hasTo($actor->email));

        $delivery = NotificationDelivery::query()->where('recipient_email', $member->email)->firstOrFail();

        $this->assertSame('project_activity', $delivery->notification_type);
        $this->assertSame(NotificationDelivery::STATUS_DELIVERED, $delivery->status);
        $this->assertSame('member_added', $delivery->context['event']);
        $this->assertSame($project->id, $delivery->context['project_id']);
        $this->assertSame($actor->id, $delivery->context['actor_id']);
    }

    public function test_actor_who_is_also_the_owner_is_not_notified_about_their_own_action(): void
    {
        $actor = $this->systemUser('SUPER_ADMIN');
        $project = Project::factory()->withoutOwner()->create();
        $project->forceFill(['owner_id' => $actor->id, 'owner_assignment_type' => 'permanent'])->save();

        $member = User::factory()->create();

        $this->app->make(AddProjectMemberAction::class)
            ->execute($actor, $project, $member, $this->projectRole('PROJECT_VIEWER')->id, self::REASON);

        Mail::assertSent(ProjectActivityMailable::class, fn (ProjectActivityMailable $mail): bool => $mail->hasTo($member->email));
        Mail::assertNotSent(ProjectActivityMailable::class, fn (ProjectActivityMailable $mail): bool => $mail->hasTo($actor->email));
    }

    public function test_disabled_preference_records_suppression_instead_of_sending(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $member = User::factory()->create();

        UserNotificationPreference::query()->create([
            'user_id' => $member->id,
            'project_activity_enabled' => false,
            'system_announcements_enabled' => true,
        ]);

        $this->app->make(AddProjectMemberAction::class)
            ->execute($actor, $project, $member, $this->projectRole('PROJECT_VIEWER')->id, self::REASON);

        Mail::assertNothingSent();

        $this->assertDatabaseHas('notification_deliveries', [
            'user_id' => $member->id,
            'notification_type' => 'project_activity',
            'status' => NotificationDelivery::STATUS_SUPPRESSED,
            'suppression_reason' => NotificationDelivery::SUPPRESSION_REASON_PREFERENCE_DISABLED,
        ]);
    }

    public function test_member_removal_notifies_member_and_owner(): void
    {
        $project = Project::factory()->create();
        $owner = $project->owner;
        $actor = $this->systemUser('SUPER_ADMIN');
        $member = $this->attachMember($project, 'PROJECT_VIEWER');

        $this->app->make(RemoveProjectMemberAction::class)
            ->execute($actor, $project, $member, self::REASON);

        Mail::assertSent(ProjectActivityMailable::class, fn (ProjectActivityMailable $mail): bool => $mail->hasTo($member->email));
        Mail::assertSent(ProjectActivityMailable::class, fn (ProjectActivityMailable $mail): bool => $mail->hasTo($owner->email));
    }

    public function test_role_change_notifies_member_and_owner(): void
    {
        $project = Project::factory()->create();
        $owner = $project->owner;
        $actor = $this->systemUser('SUPER_ADMIN');
        $member = $this->attachMember($project, 'PROJECT_VIEWER');

        $this->app->make(ChangeProjectMemberRoleAction::class)
            ->execute($actor, $project, $member, $this->projectRole('PROJECT_EDITOR')->id, self::REASON);

        Mail::assertSent(ProjectActivityMailable::class, fn (ProjectActivityMailable $mail): bool => $mail->hasTo($member->email));
        Mail::assertSent(ProjectActivityMailable::class, fn (ProjectActivityMailable $mail): bool => $mail->hasTo($owner->email));

        $delivery = NotificationDelivery::query()->where('recipient_email', $member->email)->firstOrFail();

        $this->assertSame('member_role_changed', $delivery->context['event']);
        $this->assertSame($this->projectRole('PROJECT_EDITOR')->id, $delivery->context['role_id']);
    }

    public function test_ownership_assignment_notifies_the_new_owner(): void
    {
        $project = Project::factory()->withoutOwner()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $target = $this->attachMember($project, 'PROJECT_ADMIN');

        $this->app->make(AssignProjectOwnerAction::class)
            ->execute($actor, $project, $target, 'permanent', self::REASON);

        Mail::assertSent(ProjectActivityMailable::class, fn (ProjectActivityMailable $mail): bool => $mail->hasTo($target->email));

        $delivery = NotificationDelivery::query()->where('recipient_email', $target->email)->firstOrFail();

        $this->assertSame('owner_assigned', $delivery->context['event']);
    }

    public function test_ownership_unassignment_notifies_the_previous_owner(): void
    {
        $project = Project::factory()->create();
        $previousOwner = $project->owner;
        $actor = $this->systemUser('SUPER_ADMIN');

        $this->app->make(UnassignProjectOwnerAction::class)
            ->execute($actor, $project, self::REASON);

        Mail::assertSent(ProjectActivityMailable::class, fn (ProjectActivityMailable $mail): bool => $mail->hasTo($previousOwner->email));
    }

    public function test_ownership_transfer_notifies_both_owners(): void
    {
        $project = Project::factory()->create();
        $previousOwner = $project->owner;
        $actor = $this->systemUser('SUPER_ADMIN');
        $newOwner = $this->attachMember($project, 'PROJECT_ADMIN');

        $this->app->make(TransferProjectOwnerAction::class)
            ->execute($actor, $project, $newOwner, 'permanent', self::REASON);

        Mail::assertSent(ProjectActivityMailable::class, fn (ProjectActivityMailable $mail): bool => $mail->hasTo($newOwner->email));
        Mail::assertSent(ProjectActivityMailable::class, fn (ProjectActivityMailable $mail): bool => $mail->hasTo($previousOwner->email));
    }

    public function test_failed_project_activity_delivery_can_be_rebuilt_and_resent(): void
    {
        $project = Project::factory()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $member = User::factory()->create();

        $delivery = NotificationDelivery::query()->create([
            'user_id' => $member->id,
            'recipient_email' => $member->email,
            'notification_type' => 'project_activity',
            'status' => NotificationDelivery::STATUS_FAILED,
            'context' => [
                'event' => 'member_added',
                'project_id' => $project->id,
                'actor_id' => $actor->id,
                'member_id' => $member->id,
                'role_id' => $this->projectRole('PROJECT_VIEWER')->id,
            ],
        ]);

        $closure = $this->app->make(ProjectActivityRetryService::class)->retryClosure($delivery);

        $this->assertNotNull($closure);

        $closure();

        Mail::assertSent(ProjectActivityMailable::class, fn (ProjectActivityMailable $mail): bool => $mail->hasTo($member->email));
    }

    public function test_retry_is_unavailable_without_reconstruction_context(): void
    {
        $member = User::factory()->create();

        $delivery = NotificationDelivery::query()->create([
            'user_id' => $member->id,
            'recipient_email' => $member->email,
            'notification_type' => 'project_activity',
            'status' => NotificationDelivery::STATUS_FAILED,
        ]);

        $this->assertNull(
            $this->app->make(ProjectActivityRetryService::class)->retryClosure($delivery),
        );
    }

    public function test_message_renders_the_project_activity_summary_and_link(): void
    {
        $project = Project::factory()->create();
        $actor = $this->systemUser('SUPER_ADMIN');
        $member = User::factory()->create();

        $mailable = $this->app->make(ProjectActivityMessageFactory::class)->build('member_added', $project, [
            'event' => 'member_added',
            'project_id' => $project->id,
            'actor_id' => $actor->id,
            'member_id' => $member->id,
            'role_id' => $this->projectRole('PROJECT_VIEWER')->id,
        ]);

        $this->assertNotNull($mailable);

        $body = $mailable->render();

        $this->assertStringContainsString($member->name, $body);
        $this->assertStringContainsString($project->name, $body);
        $this->assertStringContainsString(route('projects.show', $project), $body);
    }

    private function systemUser(string $code): User
    {
        $user = User::factory()->create();
        $role = Role::query()->where('code', $code)->where('scope', 'system')->firstOrFail();
        $user->role()->associate($role);
        $user->save();

        return $user;
    }

    private function projectRole(string $code): Role
    {
        return Role::query()->firstOrCreate(
            ['code' => $code, 'scope' => 'project'],
            [
                'name' => ucwords(str_replace(['.', '_'], ' ', $code)),
                'description' => 'Test helper project role.',
            ],
        );
    }

    private function attachMember(Project $project, string $roleCode): User
    {
        $member = User::factory()->create();
        $project->members()->attach($member->id, ['role_id' => $this->projectRole($roleCode)->id]);

        return $member;
    }
}
