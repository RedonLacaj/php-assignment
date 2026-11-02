<?php

namespace App\Http\Resources;

use App\Models\SyncLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SyncLog */
class SyncLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'rows_saved' => $this->rows_saved,
            'message' => $this->message,
            'started_at' => $this->started_at->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'spreadsheet' => $this->whenLoaded('spreadsheet', fn () => $this->spreadsheet?->only('id', 'name')),
        ];
    }
}
