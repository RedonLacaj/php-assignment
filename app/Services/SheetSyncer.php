<?php

namespace App\Services;

use App\Models\SheetTab;
use App\Models\Spreadsheet;
use App\Models\SyncLog;
use App\Models\TableMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SheetSyncer
{
    private array $notes = [];

    private bool $hasWarnings = false;

    public function __construct(
        private SheetReader $reader,
        private TabLocator $locator,
        private TableSchemaManager $schema,
        private ValueCaster $caster,
    ) {}

    public function sync(Spreadsheet $spreadsheet): SyncLog
    {
        $this->notes = [];
        $this->hasWarnings = false;
        $saved = 0;
        $failed = false;

        $log = $spreadsheet->syncLogs()->create(['status' => 'running', 'started_at' => now()]);

        $tabs = $spreadsheet->tabs()
            ->where('is_active', true)
            ->with(['mappings' => fn ($q) => $q->where('is_active', true)->with('columns')])
            ->get();

        if ($tabs->isEmpty()) {
            $this->warn('No active tabs configured, nothing to import');
        }

        foreach ($tabs as $tab) {
            try {
                $saved += $this->syncTab($spreadsheet, $tab);
            } catch (Throwable $e) {
                $failed = true;
                $this->notes[] = "[{$tab->name}] ".$e->getMessage();
                Log::error('Sheet sync failed', ['spreadsheet' => $spreadsheet->id, 'tab' => $tab->name, 'error' => $e->getMessage()]);
            }
        }

        $status = match (true) {
            $failed && $saved === 0 => 'failed',
            $failed || $this->hasWarnings => 'warning',
            default => 'success',
        };

        $log->update([
            'status' => $status,
            'rows_saved' => $saved,
            'message' => implode("\n", $this->notes) ?: null,
            'finished_at' => now(),
        ]);

        if ($status !== 'failed') {
            $spreadsheet->forceFill(['last_synced_at' => now()])->save();
        }

        return $log;
    }

    private function syncTab(Spreadsheet $spreadsheet, SheetTab $tab): int
    {
        if ($tab->mappings->isEmpty()) {
            $this->warn("[{$tab->name}] no active table mappings, tab skipped");

            return 0;
        }

        $tab->setRelation('spreadsheet', $spreadsheet);
        $oldName = $tab->name;
        $title = $this->locator->title($tab);

        if (filled($oldName) && $oldName !== $title) {
            $this->notes[] = "[{$title}] tab was renamed from \"{$oldName}\" in the spreadsheet";
        }

        $rows = $this->reader->rows($spreadsheet->google_id, $title);
        $headerIndex = max($tab->header_row, 1) - 1;
        $headers = new HeaderMap($rows[$headerIndex] ?? []);
        $dataRows = array_slice($rows, $headerIndex + 1);

        $saved = 0;

        foreach ($tab->mappings as $mapping) {
            $saved += $this->syncMapping($title, $mapping, $headers, $dataRows);
        }

        return $saved;
    }

    private function syncMapping(string $title, TableMapping $mapping, HeaderMap $headers, array $dataRows): int
    {
        $added = $this->schema->sync($mapping);

        if ($added) {
            $this->notes[] = "[{$title}] added columns to {$mapping->target_table}: ".implode(', ', $added);
        }

        // Columns are matched by header name, so their position in the sheet doesn't matter
        $columns = [];
        foreach ($mapping->columns as $column) {
            $position = $headers->position($column->source_column);

            if ($position === null) {
                $this->warn("[{$title}] column \"{$column->source_column}\" not found in sheet, skipped");

                continue;
            }

            $columns[] = [$position, $column->target_column, $column->type];
        }

        if (! $columns) {
            return 0;
        }

        $now = now();
        $records = [];

        foreach ($dataRows as $row) {
            $record = [];
            foreach ($columns as [$index, $target, $type]) {
                $record[$target] = $this->caster->cast($row[$index] ?? null, $type);
            }

            if (array_filter($record, fn ($v) => $v !== null) === []) {
                continue;
            }

            $records[] = $record + ['table_mapping_id' => $mapping->id, 'synced_at' => $now];
        }

        DB::transaction(function () use ($mapping, $records) {
            $query = DB::table($mapping->target_table);

            if ($mapping->mode === 'replace') {
                $query->where('table_mapping_id', $mapping->id)->delete();
            }

            foreach (array_chunk($records, config('sheets.insert_chunk')) as $chunk) {
                DB::table($mapping->target_table)->insert($chunk);
            }
        });

        return count($records);
    }

    private function warn(string $message): void
    {
        $this->notes[] = $message;
        $this->hasWarnings = true;
    }
}
