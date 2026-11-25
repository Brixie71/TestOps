<?php

namespace App\Services\Notifications;

use App\Mail\ProjectActivityMailable;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The project activity optional-category sender (A15.3-A15.4): membership
 * and ownership changes inside one project.
 *
 * The affected member and the project owner are notified, never the actor,
 * and never an inactive or soft-deleted account. Each recipient's stored
 * preference is resolved immediately before delivery; a recipient who
 * disabled the category is recorded as suppressed (never failed). Delivery
 * is synchronous and one failure never interrupts the remaining recipients.
 *
 * Every delivery stores the event and referenced ids, so a failed message
 * can be rebuilt by the retry workflow instead of persisting its body.
 */
class ProjectActivityNotificationService
{
    public const NOTIFICATION_TYPE = 'project_activity';

    public function __construct(
        private readonly ProjectActivityMessageFactory $messages,
        private readonly NotificationPreferenceChecker $preferenceChecker,
        private readonly NotificationDeliveryRecorder $recorder,
    ) {}

    public function memberAdded(Project $project, User $actor, User $member, Role $role): void
    {
        $this->send($project, $actor, [$member, $this->owner($project)], ProjectActivityMessageFactory::EVENT_MEMBER_ADDED, [
            'member_id' => $member->getKey(),
            'role_id' => $role->getKey(),
        ]);
    }

    public function memberRemoved(Project $project, User $actor, User $member, Role $role): void
    {
        $this->send($project, $actor, [$member, $this->owner($project)], ProjectActivityMessageFactory::EVENT_MEMBER_REMOVED, [
            'member_id' => $member->getKey(),
            'role_id' => $role->getKey(),
        ]);
    }

    public function memberRoleChanged(Project $project, User $actor, User $member, Role $previousRole, Role $newRole): void
    {
        $this->send($project, $actor, [$member, $this->owner($project)], ProjectActivityMessageFactory::EVENT_MEMBER_ROLE_CHANGED, [
            'member_id' => $member->getKey(),
            'previous_role_id' => $previousRole->getKey(),
            'role_id' => $newRole->getKey(),
        ]);
    }

    public function ownerAssigned(Project $project, User $actor, User $owner): void
    {
        $this->send($project, $actor, [$owner], ProjectActivityMessageFactory::EVENT_OWNER_ASSIGNED, [
            'owner_id' => $owner->getKey(),
        ]);
    }

    public function ownerUnassigned(Project $project, User $actor, ?User $previousOwner): void
    {
        $this->send($project, $actor, [$previousOwner], ProjectActivityMessageFactory::EVENT_OWNER_UNASSIGNED, [
            'owner_id' => $previousOwner?->getKey(),
        ]);
    }

    public function ownerTransferred(Project $project, User $actor, User $newOwner, ?User $previousOwner): void
    {
        $this->send($project, $actor, [$newOwner, $previousOwner], ProjectActivityMessageFactory::EVENT_OWNER_TRANSFERRED, [
            'owner_id' => $newOwner->getKey(),
            'previous_owner_id' => $previousOwner?->getKey(),
        ]);
    }

    /**
     * Notify every eligible recipient of one project event.
     *
     * @param  array<int, User|null>  $recipients
     * @param  array<string, mixed>  $context
     */
    public function send(Project $project, User $actor, array $recipients, string $event, array $context): void
    {
        $context = [
            'event' => $event,
            'project_id' => $project->getKey(),
            'actor_id' => $actor->getKey(),
            ...$context,
        ];

        $mailable = $this->messages->build($event, $project, $context);

        if ($mailable === null) {
            return;
        }

        foreach ($this->eligibleRecipients($recipients, $actor) as $recipient) {
            $this->deliver($recipient, $mailable, $context);
        }
    }

    /**
     * Record one recipient's outcome.
     *
     * @param  array<string, mixed>  $context
     */
    private function deliver(User $recipient, ProjectActivityMailable $mailable, array $context): void
    {
        $email = (string) $recipient->email;

        if (! $this->preferenceChecker->allows($recipient, UserNotificationPreference::CATEGORY_PROJECT_ACTIVITY)) {
            $this->recorder->recordSuppressed(
                $recipient,
                $email,
                self::NOTIFICATION_TYPE,
                context: $context,
            );

            return;
        }

        try {
            Mail::to($email)->send($mailable);

            $this->recorder->recordSuccess($recipient, $email, self::NOTIFICATION_TYPE, $context);
        } catch (Throwable $exception) {
            $this->recorder->recordFailure($recipient, $email, self::NOTIFICATION_TYPE, $exception, $context);
        }
    }

    /**
     * Resolve the audience: distinct active accounts, never the actor.
     *
     * @param  array<int, User|null>  $recipients
     * @return Collection<int, User>
     */
    private function eligibleRecipients(array $recipients, User $actor): Collection
    {
        return collect($recipients)
            ->filter(fn (?User $user): bool => $user instanceof User)
            ->reject(fn (User $user): bool => $user->trashed())
            ->reject(fn (User $user): bool => $user->account_status !== 'active')
            ->reject(fn (User $user): bool => (int) $user->getKey() === (int) $actor->getKey())
            ->unique(fn (User $user): int => (int) $user->getKey())
            ->values();
    }

    private function owner(Project $project): ?User
    {
        if ($project->owner_id === null) {
            return null;
        }

        return User::query()->find($project->owner_id);
    }
}
