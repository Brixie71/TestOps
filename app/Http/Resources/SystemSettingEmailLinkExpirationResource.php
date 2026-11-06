<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemSettingEmailLinkExpirationResource extends JsonResource
{
    /**
     * Transform the resource into a JSON array.
     */
    public function toArray(Request $request): array
    {
        return [
            'email_link_expiration_minutes' => $this->resource,
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
