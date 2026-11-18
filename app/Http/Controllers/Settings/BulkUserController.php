<?php

namespace App\Http\Controllers\Settings;

use App\Actions\AssignUserRoleAction;
use App\Actions\DeactivateUserAction;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Bulk user operations (§13): bulk system role assignment and bulk
 * account deactivation.
 *
 * There is no fixed user-selection limit; selections are processed in
 * manageable chunks with partial processing — every target runs through
 * the same single-user actions (AssignUserRoleAction, DeactivateUserAction)
 * and an invalid or unauthorized target is skipped with an individual
 * failure reason while the remaining targets still complete. Each target's
 * change is transactionally atomic and audited by the single-user action;
 * this controller only validates the request shape and aggregates the
 * per-user outcomes.
 */
class BulkUserController extends Controller
{
    /**
     * Number of targets loaded and processed per chunk.
     */
    private const CHUNK_SIZE = 100;

    public function __construct(
        private readonly AssignUserRoleAction $assignUserRoleAction,
        private readonly DeactivateUserAction $deactivateUserAction,
    ) {}

    /**
     * Bulk system role assignment (§13.2). Only existing system-scoped
     * roles are eligible; bulk Super Admin assignment is prohibited.
     */
    public function assignRole(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer'],
            'role_id' => [
                'required',
                'integer',
                Rule::exists('roles', 'id')->where('scope', 'system'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (Role::query()->find($value)?->code === 'SUPER_ADMIN') {
                        $fail('Bulk assignment of the Super Admin role is not allowed.');
                    }
                },
            ],
        ]);

        $results = $this->process(
            $request->user(),
            $validated['user_ids'],
            fn (User $actor, User $target): mixed => $this->assignUserRoleAction->execute(
                $actor,
                $target,
                (int) $validated['role_id'],
            ),
            'System role updated.',
        );

        return back()->with('bulk_results', $results);
    }

    /**
     * Bulk account deactivation (§13.3). One reason covers the batch; the
     * action still enforces per-target eligibility — protected accounts are
     * skipped with individual failure reasons.
     */
    public function deactivate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $results = $this->process(
            $request->user(),
            $validated['user_ids'],
            fn (User $actor, User $target): mixed => $this->deactivateUserAction->execute(
                $actor,
                $target,
                $validated['reason'],
            ),
            'Account deactivated.',
        );

        return back()->with('bulk_results', $results);
    }

    /**
     * Run one operation per selected target in chunks, collecting an
     * individual success/failure result for each (§13.1). Validation and
     * authorization rejections are recorded as that target's failure
     * reason; unexpected failures (missing canonical role row, inconsistent
     * connections) are environmental and still propagate.
     *
     * @param  array<int, int|string>  $userIds
     * @return array<int, array{name: string, email: string|null, success: bool, message: string}>
     */
    private function process(User $actor, array $userIds, Closure $operation, string $successMessage): array
    {
        $results = [];

        foreach (array_chunk(array_map(intval(...), $userIds), self::CHUNK_SIZE) as $chunk) {
            $targets = User::query()->withTrashed()->whereIn('id', $chunk)->get()->keyBy('id');

            foreach ($chunk as $targetId) {
                $target = $targets->get($targetId);

                if ($target === null) {
                    $results[] = [
                        'name' => 'User #'.$targetId,
                        'email' => null,
                        'success' => false,
                        'message' => 'This account does not exist.',
                    ];

                    continue;
                }

                try {
                    $operation($actor, $target);

                    $results[] = [
                        'name' => $target->name ?? 'User #'.$target->getKey(),
                        'email' => $target->email,
                        'success' => true,
                        'message' => $successMessage,
                    ];
                } catch (ValidationException|AuthorizationException $exception) {
                    $results[] = [
                        'name' => $target->name ?? 'User #'.$target->getKey(),
                        'email' => $target->email,
                        'success' => false,
                        'message' => $exception->getMessage(),
                    ];
                }
            }
        }

        return $results;
    }
}
