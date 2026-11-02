<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Spreadsheet extends Model
{
    protected $fillable = ['name', 'google_id', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'google_tabs' => 'array',
            'tabs_fetched_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function tabs(): HasMany
    {
        return $this->hasMany(SheetTab::class);
    }

    public function syncLogs(): HasMany
    {
        return $this->hasMany(SyncLog::class);
    }

    public function latestLog(): HasOne
    {
        return $this->hasOne(SyncLog::class)->latestOfMany('started_at');
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn () => "https://docs.google.com/spreadsheets/d/{$this->google_id}/edit");
    }

    /**
     * Accepts either a raw spreadsheet id or a full Google Sheets URL.
     */
    public static function extractGoogleId(string $value): string
    {
        if (preg_match('~/spreadsheets/d/([a-zA-Z0-9_-]+)~', $value, $m)) {
            return $m[1];
        }

        return trim($value);
    }
}
