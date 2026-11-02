<?php

namespace App\Http\Controllers;

use App\Http\Requests\SheetTabRequest;
use App\Http\Resources\SheetTabResource;
use App\Models\ColumnMapping;
use App\Models\SheetTab;
use App\Models\Spreadsheet;
use App\Services\HeaderMap;
use App\Services\SheetReader;
use App\Services\TabLocator;
use App\Services\ValueCaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Throwable;

class SheetTabController extends Controller
{
    public function store(SheetTabRequest $request, Spreadsheet $spreadsheet, TabLocator $locator)
    {
        $tab = $spreadsheet->tabs()->create($request->tabData());

        if ($error = $this->locate($tab, $locator)) {
            return back()->with('warning', "Tab added. But it couldn't be checked in Google: {$error}");
        }

        // the next step is always choosing where the data goes
        return redirect()->route('mappings.create', $tab)->with('success', 'Tab added. Now choose the table its data goes to.');
    }

    public function edit(SheetTab $tab)
    {
        $tab->load('mappings.columns', 'spreadsheet');

        return Inertia::render('tabs/edit', [
            'tab' => new SheetTabResource($tab),
            'spreadsheet' => $tab->spreadsheet->only('id', 'name'),
        ]);
    }

    public function update(SheetTabRequest $request, SheetTab $tab, TabLocator $locator)
    {
        $tab->fill($request->tabData());

        // name was changed by hand, find the tab again by the new name
        if ($tab->isDirty('name')) {
            $tab->google_sheet_id = null;
        }

        $tab->save();

        if ($error = $this->locate($tab, $locator)) {
            return back()->with('warning', "Tab updated. But it couldn't be checked in Google: {$error}");
        }

        return back()->with('success', 'Tab updated.');
    }

    public function destroy(SheetTab $tab)
    {
        $tab->delete();

        // the edit page of the tab doesn't exist anymore
        return redirect()->route('spreadsheets.show', $tab->spreadsheet_id)->with('success', 'Tab removed.');
    }

    /**
     * Used by the mapping form: header names, sample values for guessing column
     * types, and the first rows converted with the columns being edited.
     */
    public function preview(Request $request, SheetTab $tab, SheetReader $reader, TabLocator $locator, ValueCaster $caster)
    {
        try {
            // the form asks for a preview on every change, don't hit Google's rate limit
            $rows = Cache::remember("tab-rows:{$tab->id}", 60, fn () => $reader->rows($tab->spreadsheet->google_id, $locator->title($tab)));
        } catch (Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $headerRow = $rows[$tab->header_row - 1] ?? [];
        $dataRows = array_slice($rows, $tab->header_row);
        $headers = new HeaderMap($headerRow);

        $names = array_values(array_filter(array_map(fn ($h) => trim((string) $h), $headerRow), fn ($h) => $h !== ''));
        $samples = [];
        foreach ($names as $name) {
            $position = $headers->position($name);
            $samples[$name] = array_map(fn ($row) => $row[$position] ?? null, array_slice($dataRows, 0, 20));
        }

        $columns = collect(json_decode((string) $request->query('columns'), true) ?: [])
            ->filter(fn ($c) => is_array($c) && filled($c['source_column'] ?? null) && filled($c['target_column'] ?? null));

        $preview = array_map(function ($row) use ($columns, $headers, $caster) {
            $record = [];
            foreach ($columns as $column) {
                $position = $headers->position($column['source_column']);
                $type = in_array($column['type'] ?? null, ColumnMapping::TYPES, true) ? $column['type'] : 'string';

                if ($position !== null) {
                    $record[$column['target_column']] = $caster->cast($row[$position] ?? null, $type);
                }
            }

            return $record;
        }, array_slice($dataRows, 0, 5));

        return response()->json([
            'headers' => $names,
            'samples' => $samples,
            'rows' => $preview,
            'missing' => $columns->pluck('source_column')->filter(fn ($s) => $headers->position($s) === null)->values(),
        ]);
    }

    // Saves Google's tab id right away, so later renames in the file are picked up.
    // Returns the error message when the tab can't be found.
    private function locate(SheetTab $tab, TabLocator $locator): ?string
    {
        try {
            $locator->title($tab);
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        return null;
    }
}
