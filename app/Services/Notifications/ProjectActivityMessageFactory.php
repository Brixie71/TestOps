<?php

namespace App\Services\Notifications;

use App\Mail\ProjectActivityMailable;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;

/**
 * Rebuilds a project activity message from an event name and the referenced
 * ids (A15.3).
 *
 * The sender and the retry workflow both use this factory, so a failed
 * delivery can be re-rendered without storing the message body: display
 * names and the project title are resolved from the database at send time.
 * Unknown events or a deleted project return null and the caller sends
 * nothing.
 */
class ProjectActivityMessageFactory
{
    public const EVENT_MEMBER_ADDED = 'member_added';

    public const EVENT_MEMBER_REMOVED = 'member_removed';

    public const EVENT_MEMBER_ROLE_CHANGED = 'member_role_changed';

    public const EVENT_OWNER_ASSIGNED = 'owner_assigned';

    public const EVENT_OWNER_UNASSIGNED = 'owner_unassigned';

    public const EVENT_OWNER_TRANSFERRED = 'owner_transferred';

    /**
     * Events this factory can render.
     *
     * @var list<string>
     */
    public const EVENTS = [
        self::EVENT_MEMBER_ADDED,
        self::EVENT_MEMBER_REMOVED,
        self::EVENT_MEMBER_ROLE_CHANGED,
        self::EVENT_OWNER_ASSIGNED,
        self::EVENT_OWNER_UNASSIGNED,
        self::EVENT_OWNER_TRANSFERRED,
    ];

    /**
     * Build the message for one event, or null when it cannot be rendered.
     *
     * @param  array<string, mixed>  $context
     */
    public function build(string $event, Project $project, array $context): ?ProjectActivityMailable
    {
        $actorName = $this->userName($context['actor_id'] ?? null) ?? 'A project administrator';
        $summary = $this->summary($event, $actorName, $context);

        if ($summary === null) {
            return null;
        }

        return new ProjectActivityMailable(
            activitySummary: $summary,
            projectName: $project->name,
            projectUrl: route('projects.show', $project),
        );
    }

    /**
     * Compose the plain-text summary for one event.
     *
     * @param  array<string, mixed>  $context
     */
    private function summary(string $event, string $actorName, array $context): ?string
    {
        return match ($event) {
            self::EVENT_MEMBER_ADDED => sprintf(
                '%s added %s to the project as %s.',
                $actorName,
                $this->userName($context['member_id'] ?? null) ?? 'a member',
                $this->roleName($context['role_id'] ?? null) ?? 'a project member',
            ),
            self::EVENT_MEMBER_REMOVED => sprintf(
                '%s removed %s from the project.',
                $actorName,
                $this->userName($context['member_id'] ?? null) ?? 'a member',
            ),
            self::EVENT_MEMBER_ROLE_CHANGED => sprintf(
                '%s changed %s project role from %s to %s.',
                $actorName,
                $this->userName($context['member_id'] ?? null) ?? 'a member',
                $this->roleName($context['previous_role_id'] ?? null) ?? 'the previous role',
                $this->roleName($context['role_id'] ?? null) ?? 'a new role',
            ),
            self::EVENT_OWNER_ASSIGNED => sprintf(
                '%s assigned %s as the project owner.',
                $actorName,
                $this->userName($context['owner_id'] ?? null) ?? 'a member',
            ),
            self::EVENT_OWNER_UNASSIGNED => sprintf(
                '%s unassigned %s as the project owner.',
                $actorName,
                $this->userName($context['owner_id'] ?? null) ?? 'the owner',
            ),
            self::EVENT_OWNER_TRANSFERRED => sprintf(
                '%s transferred project ownership from %s to %s.',
                $actorName,
                $this->userName($context['previous_owner_id'] ?? null) ?? 'the previous owner',
                $this->userName($context['owner_id'] ?? null) ?? 'a new owner',
            ),
            default => null,
        };
    }

    private function userName(mixed $userId): ?string
    {
        if (! is_int($userId) && ! is_string($userId)) {
            return null;
        }

        return User::query()->withTrashed()->find($userId)?->name;
    }

    private function roleName(mixed $roleId): ?string
    {
        if (! is_int($roleId) && ! is_string($roleId)) {
            return null;
        }

        return Role::query()->find($roleId)?->name;
    }
}
