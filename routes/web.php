<?php

use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\Settings\UserDirectoryController;
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
    Route::view('dashboard', 'dashboard.index')->name('dashboard');
});

/*
|--------------------------------------------------------------------------
| Profile (any authenticated user) — placeholder
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::view('profile', 'profile.index')->name('profile');
});

/*
|--------------------------------------------------------------------------
| Settings (Super Admin only) — navigation destinations, no CRUD yet
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'super.admin'])->name('settings.')->group(function () {
    Route::view('settings', 'settings.index')->name('index');
    Route::get('settings/users', [UserDirectoryController::class, 'index'])
        ->name('users');
    Route::view('settings/roles', 'settings.roles')->name('roles');
    Route::view('settings/permissions', 'settings.permissions')->name('permissions');
    Route::view('settings/about', 'settings.about')->name('about');
});
