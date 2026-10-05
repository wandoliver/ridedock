<?php

namespace App\Http\Resources;

use App\Models\ProviderConnection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProviderConnection */
class ProviderConnectionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider->value,
            'external_user_id' => $this->external_user_id,
            'status' => $this->status->value,
            'scopes' => $this->scopes,
            'capabilities' => $this->capabilities,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'last_error' => $this->last_error === null ? null : 'Provider synchronization failed. Retry the synchronization to resume.',
        ];
    }
}
