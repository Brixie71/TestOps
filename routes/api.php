<?php

use App\Http\Controllers\SystemSettingEmailLinkExpirationController;
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
});
