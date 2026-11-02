<?php

namespace App\Http\Resources;

use App\Models\SheetTab;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SheetTab */
class SheetTabResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'google_sheet_id' => $this->google_sheet_id,
            'header_row' => $this->header_row,
            'is_active' => $this->is_active,
            'mappings' => TableMappingResource::collection($this->whenLoaded('mappings')),
        ];
    }
}
