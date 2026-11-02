<?php

namespace App\Http\Controllers;

use App\Http\Requests\SpreadsheetRequest;
use App\Http\Resources\SpreadsheetResource;
use App\Http\Resources\SyncLogResource;
use App\Models\Spreadsheet;
use App\Services\SheetReader;
use App\Services\SheetSyncer;
use App\Services\TabLocator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Throwable;

class SpreadsheetController extends Controller
{
    public function index()
    {
        $spreadsheets = Spreadsheet::withCount('tabs')
            ->with('latestLog')
            ->latest()
            ->latest('id')
            ->get();

        // Same setting the scheduler uses in routes/console.php
        $nextSync = today()->setTimeFromTimeString(config('sheets.sync_time'));

        if ($nextSync->isPast()) {
            $nextSync->addDay();
        }

        return Inertia::render('spreadsheets/index', [
            'spreadsheets' => SpreadsheetResource::collection($spreadsheets),
            'schedule' => [
                'time' => config('sheets.sync_time'),
                'timezone' => config('app.timezone'),
                'next_run' => $nextSync->toIso8601String(),
            ],
        ]);
    }

    public function store(SpreadsheetRequest $request, TabLocator $locator)
    {
        // One entry per file: more tabs and tables are added to the existing one
        $existing = Spreadsheet::firstWhere('google_id', $request->validated('google_id'));

        if ($existing) {
            return redirect()->route('spreadsheets.show', $existing)
                ->with('warning', "This spreadsheet is already added as \"{$existing->name}\". Add tabs or table mappings here instead.");
        }

        $spreadsheet = Spreadsheet::create($request->spreadsheetData());

        // so the "Add tab" list is ready, the page shows the problem if it fails
        rescue(fn () => $locator->tabs($spreadsheet), report: false);

        return redirect()->route('spreadsheets.show', $spreadsheet)->with('success', 'Spreadsheet added. Now add the tabs you want to import.');
    }

    public function show(Spreadsheet $spreadsheet)
    {
        $spreadsheet->load('tabs.mappings.columns');
        $logs = $spreadsheet->syncLogs()->latest('started_at')->limit(5)->get();

        return Inertia::render('spreadsheets/show', [
            'spreadsheet' => new SpreadsheetResource($spreadsheet),
            'logs' => SyncLogResource::collection($logs),
        ]);
    }

    public function update(SpreadsheetRequest $request, Spreadsheet $spreadsheet)
    {
        $spreadsheet->update($request->spreadsheetData());

        return back()->with('success', 'Spreadsheet updated.');
    }

    public function destroy(Spreadsheet $spreadsheet)
    {
        $spreadsheet->delete();

        return redirect()->route('spreadsheets.index')->with('success', 'Spreadsheet removed. Imported data was kept.');
    }

    /**
     * Used by the "Add spreadsheet" dialog: fills in the name from the title
     * in Google and tells early if the file can't be opened or is already added.
     */
    public function lookup(Request $request, SheetReader $reader)
    {
        $googleId = Spreadsheet::extractGoogleId((string) $request->query('google_id'));

        if ($googleId === '') {
            return response()->json(['message' => 'Paste the spreadsheet URL or ID.'], 422);
        }

        try {
            $title = $reader->title($googleId);
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'google_id' => $googleId,
            'title' => $title,
            'existing' => Spreadsheet::firstWhere('google_id', $googleId)?->only('id', 'name'),
        ]);
    }

    /**
     * "Fetch tab names" button: reads the tab names from Google and saves them
     * on the spreadsheet. Also picks up tabs renamed in the file.
     */
    public function fetchTabs(Spreadsheet $spreadsheet, TabLocator $locator)
    {
        try {
            $tabs = $locator->refresh($spreadsheet->load('tabs'));
        } catch (Throwable $e) {
            return back()->with('error', "Couldn't read the tabs from Google: {$e->getMessage()}");
        }

        return back()->with('success', trans_choice('Found :count tab in the spreadsheet.|Found :count tabs in the spreadsheet.', count($tabs)));
    }

    public function sync(Spreadsheet $spreadsheet, SheetSyncer $syncer)
    {
        $log = $syncer->sync($spreadsheet);

        $type = $log->status === 'failed' ? 'error' : ($log->status === 'warning' ? 'warning' : 'success');

        return back()->with($type, "Sync finished: {$log->status}, {$log->rows_saved} rows saved.".($log->message ? "\n".$log->message : ''));
    }
}
