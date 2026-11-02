<?php

namespace App\Http\Resources;

use App\Models\Spreadsheet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Spreadsheet */
class SpreadsheetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'google_id' => $this->google_id,
            'url' => $this->url,
            'is_active' => $this->is_active,
            'google_tabs' => $this->google_tabs,
            'tabs_fetched_at' => $this->tabs_fetched_at?->toIso8601String(),
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'tabs_count' => $this->whenCounted('tabs'),
            'tabs' => SheetTabResource::collection($this->whenLoaded('tabs')),
            'latest_log' => $this->whenLoaded('latestLog', fn () => $this->latestLog ? new SyncLogResource($this->latestLog) : null),
        ];
    }
}
