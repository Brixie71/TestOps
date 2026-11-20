<?php

use App\Http\Controllers\Auth\EmailChangeVerificationController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForcedPasswordResetController;
use App\Http\Controllers\Auth\InvitationAcceptanceController;
use App\Http\Controllers\Auth\InvitationRecoveryController;
use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\Projects\ProjectMembershipController;
use App\Http\Controllers\Projects\ProjectOwnershipController;
use App\Http\Controllers\Settings\AuditLogController;
use App\Http\Controllers\Settings\BulkUserController;
use App\Http\Controllers\Settings\NotificationDeliveryHistoryController;
use App\Http\Controllers\Settings\UserDeletionController;
use App\Http\Controllers\Settings\UserDirectoryController;
use App\Http\Controllers\Settings\UserEmailChangeController;
use App\Http\Controllers\Settings\UserInvitationController;
use App\Http\Controllers\Settings\UserLifecycleController;
use App\Http\Controllers\Settings\UserPasswordController;
use App\Http\Controllers\Settings\UserProfileController;
use App\Http\Controllers\Settings\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

/*
|--------------------------------------------------------------------------
| Account creation & invitations — guest acceptance flows
|--------------------------------------------------------------------------
|
| Public routes for invitation acceptance (also password setup for direct
| accounts), request recovery, and account email verification. They run
| before `auth` and therefore never touch the active-account middleware;
| every POST is throttled per IP, and all credentials travel inside the
| emailed links only.
|
*/

Route::middleware('throttle:20,1')->group(function (): void {
    Route::get('invitations/accept/{token}', [InvitationAcceptanceController::class, 'show'])
        ->name('invitations.accept.show');
});

Route::middleware('throttle:10,1')->group(function (): void {
    Route::post('invitations/accept/{token}', [InvitationAcceptanceController::class, 'store'])
        ->name('invitations.accept.store');

    Route::get('invitations/recover', [InvitationRecoveryController::class, 'show'])
        ->name('invitations.recover.show');

    Route::post('invitations/recover', [InvitationRecoveryController::class, 'store'])
        ->name('invitations.recover.store');

    Route::get('email-verification/verify', [EmailVerificationController::class, 'show'])
        ->name('email-verification.show');

    // The confirmation POST consumes the token; CSRF is verified and the
    // endpoint is throttled per IP like every guest POST here.
    Route::post('email-verification/verify', [EmailVerificationController::class, 'store'])
        ->name('email-verification.store');

    // Forced password reset (§11.3): the emailed single-use link authorizes
    // the reset; failed completion attempts disable the token.
    Route::get('password-reset/{token}', [ForcedPasswordResetController::class, 'show'])
        ->name('password-reset.show');

    Route::post('password-reset/{token}', [ForcedPasswordResetController::class, 'store'])
        ->name('password-reset.store');

    // Self-service email change confirmation (§9.4): the emailed single-use
    // link authorizes the change; GET never consumes the token.
    Route::get('email-change/confirm/{token}', [EmailChangeVerificationController::class, 'show'])
        ->name('email-change.confirm.show');

    Route::post('email-change/confirm/{token}', [EmailChangeVerificationController::class, 'store'])
        ->name('email-change.confirm.store');
});

/*
|--------------------------------------------------------------------------
| Dashboard (any authenticated user) — placeholder
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    // Minimal authenticated landing page. Dashboard features are deferred.
    Route::middleware('active.account')->group(function (): void {
        Route::view('dashboard', 'dashboard.index')->name('dashboard');
    });

    // Individual project read pages. Visibility is backend-enforced: index()
    // scopes the directory per role and show() gates through project:view
    // (owner, or member with the projects.view permission). super.admin
    // access stays available to Super Admins via the permission checker.
    Route::middleware('active.account')->group(function (): void {
        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('projects/{project}', [ProjectController::class, 'show'])->name('projects.show');
    });
});

/*
|--------------------------------------------------------------------------
| Profile (any authenticated user) — placeholder
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::middleware('active.account')->group(function (): void {
        Route::get('profile', [ProfileController::class, 'edit'])->name('profile');

        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');

        Route::patch('profile/password', [ProfileController::class, 'updatePassword'])
            ->middleware('throttle:10,1')
            ->name('profile.password.update');

        Route::post('profile/email', [ProfileController::class, 'requestEmailChange'])
            ->middleware('throttle:10,1')
            ->name('profile.email.update');

        Route::post('profile/email/cancel', [ProfileController::class, 'cancelEmailChange'])
            ->middleware('throttle:10,1')
            ->name('profile.email.cancel');
    });
});

/*
|--------------------------------------------------------------------------
| Settings (Super Admin only) — navigation destinations, no CRUD yet
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'super.admin'])->name('settings.')->group(function () {
    Route::middleware('active.account')->group(function (): void {
        Route::view('settings', 'settings.index')->name('index');
        Route::view('settings/roles', 'settings.roles')->name('roles');
        Route::view('settings/permissions', 'settings.permissions')->name('permissions');
        Route::view('settings/about', 'settings.about')->name('about');
    });
});

/*
|--------------------------------------------------------------------------
| User management (Super Admin, and System Admins with `users.deactivate`)
|--------------------------------------------------------------------------
|
| Session-terminating lifecycle endpoints and the user directory run after
| `auth` and after the active-account middleware. The
| `permission:users.deactivate` middleware authorizes Super Admin
| (unrestricted) and System Admins holding the seeded `users.deactivate`
| system permission; every remaining restriction (self-action, protected
| target roles, the last active Super Admin) is enforced server-side in the
| lifecycle actions and reflected in the directory view.
|
*/

Route::middleware(['auth', 'active.account', 'permission:users.deactivate'])->group(function (): void {
    Route::get('settings/users', [UserDirectoryController::class, 'index'])
        ->name('settings.users');

    // Registered before the {user} routes so "bulk" is not captured as a
    // user id (Laravel matches in registration order).
    Route::post('settings/users/bulk/deactivate', [BulkUserController::class, 'deactivate'])
        ->name('settings.users.bulk.deactivate');

    // Details page: `{user}` is resolved manually (including soft-deleted
    // rows) so deactivated accounts stay viewable.
    Route::get('settings/users/{user}', [UserDirectoryController::class, 'show'])
        ->name('settings.users.show');

    Route::post('settings/users/{user}/deactivate', [UserLifecycleController::class, 'deactivate'])
        ->name('settings.users.deactivate');

    Route::post('settings/users/{user}/reactivate', [UserLifecycleController::class, 'reactivate'])
        ->name('settings.users.reactivate')
        ->withTrashed();
});

/*
|--------------------------------------------------------------------------
| Permanent account deletion (Super Admin only) — §7.3
|--------------------------------------------------------------------------
|
| Permanently deleting an account is irreversible and permanently reserved
| to Super Admin; the action re-checks that restriction server-side. Only
| previously deactivated accounts qualify, the acting Super Admin confirms
| their own password and records a reason, and restrictive dependencies
| (currently owned projects) block the deletion until they are resolved.
|
*/

Route::middleware(['auth', 'active.account', 'super.admin'])->group(function (): void {
    Route::delete('settings/users/{user}/permanent', [UserDeletionController::class, 'destroy'])
        ->name('settings.users.permanent-delete')
        ->withTrashed();
});

/*
|--------------------------------------------------------------------------
| System role assignment (Super Admin, and System Admins with `users.roles.assign`)
|--------------------------------------------------------------------------
|
| The POST endpoint validates the request shape; AssignUserRoleAction owns
| the transaction, the canonical SUPER_ADMIN row lock, the authorization
| invariants (self-change, protected targets, only Super Admin grants
| SUPER_ADMIN), and the `user.role_changed` audit.
|
*/

Route::middleware(['auth', 'active.account', 'permission:users.roles.assign'])->group(function (): void {
    // Registered before the {user} route so "bulk" is not captured as a
    // user id (Laravel matches in registration order). Bulk Super Admin
    // assignment is prohibited in the controller validation (§13.2).
    Route::post('settings/users/bulk/role', [BulkUserController::class, 'assignRole'])
        ->name('settings.users.bulk.role');

    Route::post('settings/users/{user}/role', [UserRoleController::class, 'update'])
        ->name('settings.users.role.update');
});

Route::middleware(['auth', 'active.account', 'permission:users.password.manage'])->group(function (): void {
    Route::post('settings/users/{user}/password', [UserPasswordController::class, 'update'])
        ->name('settings.users.password.update');

    Route::post('settings/users/{user}/password/force-reset', [UserPasswordController::class, 'forceReset'])
        ->name('settings.users.password.force-reset');
});

/*
|--------------------------------------------------------------------------
| Administrative profile editing (Super Admin, and System Admins with
| `users.profile.manage`) — §3.1, §6.3
|--------------------------------------------------------------------------
|
| The PATCH endpoint validates the request shape; UpdateUserProfileAction
| owns the transaction, the self-management restriction, the protected-
| target restrictions, and the `user.profile_updated` audit. Deactivated
| accounts are excluded by the implicit binding, so they must be
| reactivated before their profile can be edited.
|
*/

Route::middleware(['auth', 'active.account', 'permission:users.profile.manage'])->group(function (): void {
    Route::patch('settings/users/{user}/profile', [UserProfileController::class, 'update'])
        ->name('settings.users.profile.update');
});

/*
|--------------------------------------------------------------------------
| Pending email-change cancellation (Super Admin, and System Admins with
| `users.email.manage`) — §9.6
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'active.account', 'permission:users.email.manage'])->group(function (): void {
    Route::post('settings/users/{user}/email-change/cancel', [UserEmailChangeController::class, 'cancel'])
        ->name('settings.users.email-change.cancel');
});

/*
|--------------------------------------------------------------------------
| Audit logs (Super Admin, and System Admins with `audit.view`)
|--------------------------------------------------------------------------
|
| §12.4: dedicated audit log page with filtering (§12.5) and CSV plus PDF
| exports that use the same active filters. Every viewing and exporting
| action itself generates an audit event inside the controller.
|
*/

Route::middleware(['auth', 'active.account', 'permission:audit.view'])->group(function (): void {
    Route::get('settings/audit-logs', [AuditLogController::class, 'index'])
        ->name('settings.audit-logs');

    Route::get('settings/audit-logs/export', [AuditLogController::class, 'export'])
        ->name('settings.audit-logs.export');

    Route::get('settings/audit-logs/export/pdf', [AuditLogController::class, 'exportPdf'])
        ->name('settings.audit-logs.export.pdf');
});

/*
|--------------------------------------------------------------------------
| Email delivery history (Super Admin, and System Admins with `notifications.view`)
|--------------------------------------------------------------------------
|
| §15: dedicated delivery history page and manual retry for eligible failed
| deliveries. The retry endpoint validates the resend path up front, then
| delegates the attempt, the 3-retries/5-minute-cooldown policy, the
| immutable original records, and the `notification.retry` audit to
| NotificationDeliveryRetryService.
|
*/

Route::middleware(['auth', 'active.account', 'permission:notifications.view'])->group(function (): void {
    Route::get('settings/notifications', [NotificationDeliveryHistoryController::class, 'index'])
        ->name('settings.notifications');

    Route::post('settings/notifications/{delivery}/retry', [NotificationDeliveryHistoryController::class, 'retry'])
        ->name('settings.notifications.retry');
});

/*
|--------------------------------------------------------------------------
| Project membership & ownership management
|--------------------------------------------------------------------------
|
| Session-based mutation endpoints delegating to the Stage 2 actions. The
| actions remain authoritative: they own the transaction, the project-row
| locking, the authorization re-evaluation through ProjectPolicy, the
| invariants, and the audit records. Controllers only validate request
| shape and pass explicit scalar values.
|
| Membership routes deliberately carry no `permission:*` middleware:
| EnsurePermission is system-scope only and would wrongly deny
| PROJECT_ADMIN users whose authority is per-project inside ProjectPolicy.
|
| Ownership routes add the system-scope `permission:projects.manage-ownership`
| as an early gate (Super Admin unrestricted, System Admin with the seeded
| permission); the actions still re-authorize every request.
|
*/

Route::middleware(['auth', 'active.account'])->group(function (): void {
    // Account creation through invitations requires the seeded system
    // permission `users.create` (Super Admin unrestricted, System Admin
    // with the seeded permission); lifecycle management of live
    // invitations requires `users.invitations.manage`. Actions remain the
    // authoritative layer and re-validate every request.
    Route::middleware('permission:users.create')->group(function (): void {
        Route::post('settings/users/invitations', [UserInvitationController::class, 'store'])
            ->name('settings.users.invitations.store');
    });

    Route::middleware('permission:users.invitations.manage')->prefix('settings/users/invitations')->group(function (): void {
        Route::post('{invitation}/resend', [UserInvitationController::class, 'resend'])
            ->name('settings.users.invitations.resend');

        Route::post('{invitation}/cancel', [UserInvitationController::class, 'cancel'])
            ->name('settings.users.invitations.cancel');
    });

    Route::post('projects/{project}/members', [ProjectMembershipController::class, 'add'])
        ->name('projects.members.add');

    Route::post('projects/{project}/members/{member}/role', [ProjectMembershipController::class, 'changeRole'])
        ->name('projects.members.change-role')
        ->withTrashed();

    Route::post('projects/{project}/members/{member}/remove', [ProjectMembershipController::class, 'remove'])
        ->name('projects.members.remove')
        ->withTrashed();

    Route::post('projects/{project}/ownership/assign', [ProjectOwnershipController::class, 'assign'])
        ->middleware('permission:projects.manage-ownership')
        ->name('projects.ownership.assign');

    Route::post('projects/{project}/ownership/transfer', [ProjectOwnershipController::class, 'transfer'])
        ->middleware('permission:projects.manage-ownership')
        ->name('projects.ownership.transfer');

    Route::post('projects/{project}/ownership/unassign', [ProjectOwnershipController::class, 'unassign'])
        ->middleware('permission:projects.manage-ownership')
        ->name('projects.ownership.unassign');

    Route::post('projects/{project}/ownership/assignment-type', [ProjectOwnershipController::class, 'changeAssignmentType'])
        ->middleware('permission:projects.manage-ownership')
        ->name('projects.ownership.change-type');

    Route::post('projects/{project}/ownership/auto-assignment', [ProjectOwnershipController::class, 'changeAutoAssignment'])
        ->middleware('permission:projects.manage-ownership')
        ->name('projects.ownership.change-auto-assignment');
});
