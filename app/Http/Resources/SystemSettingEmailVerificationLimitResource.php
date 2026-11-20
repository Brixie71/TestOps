<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Present the configurable email-verification limits.
 *
 * @phpstan-property array{ip_hourly_limit:int} $resource
 */
class SystemSettingEmailVerificationLimitResource extends JsonResource
{
    /**
     * Transform the resource into a JSON array.
     *
     * @return array<string, int>
     */
    public function toArray(Request $request): array
    {
        return [
            'email_verification_ip_hourly_limit' => $this->resource['ip_hourly_limit'],
        ];
    }

    /**
     * Complete the { data, meta, links } response envelope; meta stays empty
     * until there is real metadata to expose.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [],
            'links' => [],
        ];
    }
}
