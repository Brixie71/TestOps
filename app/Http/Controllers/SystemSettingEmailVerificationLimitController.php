<?php

namespace App\Http\Controllers;

use App\Http\Resources\SystemSettingEmailVerificationLimitResource;
use App\Services\Settings\EmailVerificationLimitSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SystemSettingEmailVerificationLimitController extends Controller
{
    /**
     * Input name for the configurable per-IP hourly issuance limit.
     */
    private const INPUT_KEY = 'email_verification_ip_hourly_limit';

    /**
     * Show the configured email-verification limits.
     */
    public function show(
        EmailVerificationLimitSettings $settings
    ): SystemSettingEmailVerificationLimitResource {
        return new SystemSettingEmailVerificationLimitResource([
            'ip_hourly_limit' => $settings->getIpHourlyLimit(),
        ]);
    }

    /**
     * Update the per-IP hourly issuance limit.
     *
     * Machine clients are always denied at the authorization middleware;
     * writes remain reserved for the Super Admin role. An unchanged value
     * writes and audits nothing.
     */
    public function update(
        Request $request,
        EmailVerificationLimitSettings $settings
    ): SystemSettingEmailVerificationLimitResource {
        $actor = $request->user() ?? Auth::guard('api')->user();

        if ($request->has(self::INPUT_KEY)) {
            $settings->updateIpHourlyLimit((int) $request->input(self::INPUT_KEY), $actor);
        }

        return new SystemSettingEmailVerificationLimitResource([
            'ip_hourly_limit' => $settings->getIpHourlyLimit(),
        ]);
    }
}
