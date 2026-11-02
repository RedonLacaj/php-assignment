<?php

namespace App\Http\Controllers;

use App\Http\Requests\TableMappingRequest;
use App\Http\Resources\TableMappingResource;
use App\Models\SheetTab;
use App\Models\TableMapping;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TableMappingController extends Controller
{
    public function create(SheetTab $tab)
    {
        return $this->form($tab, null);
    }

    public function store(TableMappingRequest $request, SheetTab $tab)
    {
        DB::transaction(function () use ($request, $tab) {
            $mapping = $tab->mappings()->create($request->mappingData());
            $mapping->columns()->createMany($request->columnData());
        });

        return redirect()->route('tabs.edit', $tab)->with('success', 'Mapping saved.');
    }

    public function edit(TableMapping $mapping)
    {
        return $this->form($mapping->tab, $mapping->load('columns'));
    }

    public function update(TableMappingRequest $request, TableMapping $mapping)
    {
        // Columns removed here stay in the DB table, only the mapping is dropped
        DB::transaction(function () use ($request, $mapping) {
            $mapping->update($request->mappingData());
            $mapping->columns()->delete();
            $mapping->columns()->createMany($request->columnData());
        });

        return redirect()->route('tabs.edit', $mapping->tab)->with('success', 'Mapping updated.');
    }

    public function destroy(TableMapping $mapping)
    {
        $mapping->delete();

        return back()->with('success', 'Mapping removed. The table and its data were kept.');
    }

    private function form(SheetTab $tab, ?TableMapping $mapping)
    {
        return Inertia::render('mappings/form', [
            'tab' => [
                'id' => $tab->id,
                'name' => $tab->name,
                'spreadsheet' => $tab->spreadsheet->only('id', 'name'),
            ],
            'mapping' => $mapping ? new TableMappingResource($mapping) : null,
        ]);
    }
}
