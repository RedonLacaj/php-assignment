<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TableMapping extends Model
{
    // replace: rows imported earlier by this mapping are deleted and re-imported
    // append:  every sync adds a new snapshot of the tab (keeps daily history)
    public const MODES = ['replace', 'append'];

    protected $fillable = ['target_table', 'mode', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function tab(): BelongsTo
    {
        return $this->belongsTo(SheetTab::class, 'sheet_tab_id');
    }

    public function columns(): HasMany
    {
        return $this->hasMany(ColumnMapping::class)->orderBy('id');
    }
}
