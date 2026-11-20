<?php

namespace App\Actions;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Users\UserLifecycleAuthorizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Update a user's own or administrative profile fields (§3.1, §6.3, §6.5).
 *
 * Only the profile attributes are ever written: name, contact number, job
 * title, and department. Email changes, passwords, system roles, and account
 * status have their own approved workflows and can never be changed through
 * this action, even if extra input is submitted.
 *
 * Users may edit their own profile. Editing another account requires the
 * `users.profile.manage` permission and is subject to the protected-target
 * restrictions (a System Admin may never edit a Super Admin or another
 * System Admin). Deactivated accounts must be reactivated first. Every
 * changed field is audited as `user.profile_updated` with before/after
 * snapshots; an unchanged submission writes and audits nothing.
 */
class UpdateUserProfileAction
{
    /**
     * Profile attributes this action is allowed to write.
     *
     * @var array<int, string>
     */
    private const PROFILE_ATTRIBUTES = [
        'name',
        'contact_number',
        'job_title',
        'department',
    ];

    public function __construct(
        private readonly UserLifecycleAuthorizer $authorizer,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException when the target does not exist or is deactivated
     * @throws AuthorizationException when the actor is not authorized
     */
    public function execute(User $actor, User $target, array $attributes): void
    {
        $values = $this->normalize($attributes);

        DB::transaction(function () use ($actor, $target, $values): void {
            // The participants may have been loaded before the transaction;
            // reload them so the checks use current database state.
            $target = User::query()->withTrashed()->find($target->getKey());
            $actor = User::query()->find($actor->getKey());

            if ($actor === null) {
                throw new AuthorizationException(
                    'You are not authorized to edit user profiles.'
                );
            }

            if ($target === null) {
                throw ValidationException::withMessages([
                    'user' => 'This account no longer exists.',
                ]);
            }

            $this->authorizer->assertCanUpdateProfile($actor, $target);

            if ($target->trashed()) {
                throw ValidationException::withMessages([
                    'user' => 'Reactivate this account before editing its profile.',
                ]);
            }

            $beforeValues = $this->snapshot($target);

            $changes = array_filter(
                $values,
                fn (mixed $value, string $attribute): bool => $target->{$attribute} !== $value,
                ARRAY_FILTER_USE_BOTH,
            );

            if ($changes === []) {
                return;
            }

            $target->forceFill($changes)->save();

            $this->auditLogger->log(
                $actor,
                $target,
                'user.profile_updated',
                $beforeValues,
                $this->snapshot($target),
            );
        });
    }

    /**
     * Keep only the approved profile attributes, trimmed, with blank optional
     * values stored as null.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{name: ?string, contact_number: ?string, job_title: ?string, department: ?string}
     */
    private function normalize(array $attributes): array
    {
        $values = [];

        foreach (self::PROFILE_ATTRIBUTES as $attribute) {
            $value = $attributes[$attribute] ?? null;

            if (is_string($value)) {
                $value = trim($value);
                $value = $value === '' ? null : $value;
            }

            $values[$attribute] = $value;
        }

        /** @var array{name: ?string, contact_number: ?string, job_title: ?string, department: ?string} $values */
        return $values;
    }

    /**
     * @return array{name: ?string, contact_number: ?string, job_title: ?string, department: ?string}
     */
    private function snapshot(User $user): array
    {
        return [
            'name' => $user->name,
            'contact_number' => $user->contact_number,
            'job_title' => $user->job_title,
            'department' => $user->department,
        ];
    }
}
