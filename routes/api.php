<?php

use App\Http\Controllers\SystemSettingAccountLinkExpirationController;
use App\Http\Controllers\SystemSettingEmailLinkExpirationController;
use App\Http\Controllers\SystemSettingEmailVerificationLimitController;
use App\Http\Middleware\EnsureEmailLinkExpirationAccess;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Machine clients (Passport client credentials) may read the email-link
    // expiration setting with the required OAuth scope and service
    // permission; human users authenticate with their OAuth user token and
    // keep the existing RBAC rules. Machine-client write access stays
    // disabled in this release; updates remain Super Admin only.
    Route::get(
        'system-settings/email-link-expiration',
        [SystemSettingEmailLinkExpirationController::class, 'show'],
    )
        ->middleware(EnsureEmailLinkExpirationAccess::using('read'))
        ->name('system-settings.email-link-expiration.show');

    Route::put(
        'system-settings/email-link-expiration',
        [SystemSettingEmailLinkExpirationController::class, 'update'],
    )
        ->middleware(EnsureEmailLinkExpirationAccess::using('write'))
        ->name('system-settings.email-link-expiration.update');

    // The three account-link expiration settings reuse the exact same
    // authorization rules with their own view permission: human reads need
    // `settings.account_link_expiration.view`, writes stay Super Admin only,
    // and machine clients may read but never write.
    Route::get(
        'system-settings/account-link-expirations',
        [SystemSettingAccountLinkExpirationController::class, 'show'],
    )
        ->middleware(EnsureEmailLinkExpirationAccess::using(
            'read',
            'settings.account_link_expiration.view',
        ))
        ->name('system-settings.account-link-expirations.show');

    Route::put(
        'system-settings/account-link-expirations',
        [SystemSettingAccountLinkExpirationController::class, 'update'],
    )
        ->middleware(EnsureEmailLinkExpirationAccess::using(
            'write',
            'settings.account_link_expiration.view',
        ))
        ->name('system-settings.account-link-expirations.update');

    // The configurable email-verification limits (§10.7) reuse the same
    // authorization rules: human reads need the account-link view
    // permission, writes stay Super Admin only, and machine clients may read
    // but never write.
    Route::get(
        'system-settings/email-verification-limits',
        [SystemSettingEmailVerificationLimitController::class, 'show'],
    )
        ->middleware(EnsureEmailLinkExpirationAccess::using(
            'read',
            'settings.account_link_expiration.view',
        ))
        ->name('system-settings.email-verification-limits.show');

    Route::put(
        'system-settings/email-verification-limits',
        [SystemSettingEmailVerificationLimitController::class, 'update'],
    )
        ->middleware(EnsureEmailLinkExpirationAccess::using(
            'write',
            'settings.account_link_expiration.view',
        ))
        ->name('system-settings.email-verification-limits.update');
});
