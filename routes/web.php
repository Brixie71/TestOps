<?php

use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\Projects\ProjectMembershipController;
use App\Http\Controllers\Projects\ProjectOwnershipController;
use App\Http\Controllers\Settings\UserDirectoryController;
use App\Http\Controllers\Settings\UserLifecycleController;
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
| Dashboard (any authenticated user) — placeholder
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    // Minimal authenticated landing page. Dashboard features are deferred.
    Route::middleware('active.account')->group(function (): void {
        Route::view('dashboard', 'dashboard.index')->name('dashboard');
    });

    // Individual project read pages. Interim lock (Decision E): until
    // ProjectController gains its real visibility scope in index() and an
    // authorization call in show(), exposing these routes to every
    // authenticated user would leak all projects and member data. The
    // super.admin middleware is the cheapest lock with existing
    // infrastructure; remove it when the real scope lands (OpenCode's first
    // implementation task).
    Route::middleware('super.admin')->group(function (): void {
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
        Route::view('profile', 'profile.index')->name('profile');
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

    Route::post('settings/users/{user}/deactivate', [UserLifecycleController::class, 'deactivate'])
        ->name('settings.users.deactivate');

    Route::post('settings/users/{user}/reactivate', [UserLifecycleController::class, 'reactivate'])
        ->name('settings.users.reactivate')
        ->withTrashed();
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
