<?php

namespace App\Http\Controllers;

use App\Http\Resources\SystemSettingEmailLinkExpirationResource;
use App\Services\Settings\EmailLinkExpirationSetting;
use Illuminate\Http\Request;

class SystemSettingEmailLinkExpirationController extends Controller
{
    /**
     * Show the configured email-link expiration duration.
     */
    public function show(EmailLinkExpirationSetting $setting): SystemSettingEmailLinkExpirationResource
    {
        return new SystemSettingEmailLinkExpirationResource(
            $setting->getMinutes(),
        );
    }

    /**
     * Update the configured email-link expiration duration.
     *
     * Machine clients are always denied at the authorization middleware;
     * this action remains reserved for the Super Admin role.
     */
    public function update(
        Request $request,
        EmailLinkExpirationSetting $setting
    ): SystemSettingEmailLinkExpirationResource {
        $minutes = $setting->updateMinutes(
            (int) $request->input('minutes'),
        );

        return new SystemSettingEmailLinkExpirationResource($minutes);
    }
}
