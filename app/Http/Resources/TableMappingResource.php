<?php

namespace App\Http\Resources;

use App\Models\TableMapping;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TableMapping */
class TableMappingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'target_table' => $this->target_table,
            'mode' => $this->mode,
            'is_active' => $this->is_active,
            'columns' => $this->whenLoaded('columns', fn () => $this->columns->map->only('source_column', 'target_column', 'type')),
        ];
    }
}
