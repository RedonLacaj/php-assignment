<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ColumnMapping extends Model
{
    public const TYPES = ['string', 'text', 'integer', 'decimal', 'boolean', 'date', 'datetime'];

    protected $fillable = ['source_column', 'target_column', 'type'];

    public function mapping(): BelongsTo
    {
        return $this->belongsTo(TableMapping::class, 'table_mapping_id');
    }
}
