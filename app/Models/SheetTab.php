<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SheetTab extends Model
{
    protected $fillable = ['name', 'google_sheet_id', 'header_row', 'is_active'];

    protected function casts(): array
    {
        return [
            'google_sheet_id' => 'integer',
            'header_row' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function spreadsheet(): BelongsTo
    {
        return $this->belongsTo(Spreadsheet::class);
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(TableMapping::class);
    }
}
