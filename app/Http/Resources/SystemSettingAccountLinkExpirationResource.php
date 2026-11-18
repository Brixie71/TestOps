<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Present the three account-link expiration settings; the resource is the
 * array of effective values by shared input name.
 *
 * @phpstan-property array{invitation_link_minutes:int, recovery_code_minutes:int, email_verification_minutes:int} $resource
 */
class SystemSettingAccountLinkExpirationResource extends JsonResource
{
    /**
     * Transform the resource into a JSON array.
     *
     * @return array<string, int>
     */
    public function toArray(Request $request): array
    {
        $minutes = $this->resource;

        return [
            'invitation_link_expiration_minutes' => $minutes['invitation_link_minutes'],
            'recovery_code_expiration_minutes' => $minutes['recovery_code_minutes'],
            'email_verification_expiration_minutes' => $minutes['email_verification_minutes'],
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
