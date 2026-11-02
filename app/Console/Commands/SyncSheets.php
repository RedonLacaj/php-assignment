<?php

namespace App\Console\Commands;

use App\Models\Spreadsheet;
use App\Services\SheetSyncer;
use Illuminate\Console\Command;

class SyncSheets extends Command
{
    protected $signature = 'sheets:sync {spreadsheet?* : ids of spreadsheets to sync, all active ones by default}';

    protected $description = 'Import data from the configured Google Sheets into the database';

    public function handle(SheetSyncer $syncer): int
    {
        $query = Spreadsheet::query();

        if ($ids = $this->argument('spreadsheet')) {
            $query->whereIn('id', $ids);
        } else {
            $query->where('is_active', true);
        }

        $spreadsheets = $query->get();

        if ($spreadsheets->isEmpty()) {
            $this->warn('Nothing to sync.');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($spreadsheets as $spreadsheet) {
            $log = $syncer->sync($spreadsheet);

            $line = sprintf('%s: %s, %d rows', $spreadsheet->name, $log->status, $log->rows_saved);
            $log->status === 'failed' ? $this->error($line) : $this->info($line);

            if ($log->message) {
                $this->line('  '.str_replace("\n", "\n  ", $log->message));
            }

            $failed += $log->status === 'failed' ? 1 : 0;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
