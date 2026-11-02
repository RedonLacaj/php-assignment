<?php

namespace App\Services;

use App\Models\SheetTab;
use App\Models\Spreadsheet;
use RuntimeException;

/**
 * Finds the current name of a configured tab in the file.
 *
 * Tabs are tracked by Google's tab id once it is known, so renaming a tab in
 * the spreadsheet doesn't break the sync.
 */
class TabLocator
{
    private array $tabs = [];

    public function __construct(private SheetReader $reader) {}

    public function title(SheetTab $tab): string
    {
        $tabs = $this->tabs($tab->spreadsheet);

        if ($tab->google_sheet_id !== null) {
            if (! isset($tabs[$tab->google_sheet_id])) {
                throw new RuntimeException('Tab was deleted from the spreadsheet');
            }

            $title = $tabs[$tab->google_sheet_id];

            if ($tab->name !== $title) {
                $tab->update(['name' => $title]);
            }

            return $title;
        }

        $id = array_search($tab->name, $tabs, true);

        if ($id === false) {
            throw new RuntimeException("Tab \"{$tab->name}\" not found in the spreadsheet");
        }

        $tab->update(['google_sheet_id' => $id]);

        return $tab->name;
    }

    /**
     * Reads the tabs of the file again and picks up tabs renamed since the last time.
     */
    public function refresh(Spreadsheet $spreadsheet): array
    {
        unset($this->tabs[$spreadsheet->google_id]);
        $tabs = $this->tabs($spreadsheet);

        foreach ($spreadsheet->tabs as $tab) {
            rescue(fn () => $this->title($tab->setRelation('spreadsheet', $spreadsheet)), report: false);
        }

        return $tabs;
    }

    /**
     * Tabs of the file as [tab id => name], read from Google once per instance.
     * The names are saved on the spreadsheet for the "Add tab" list.
     */
    public function tabs(Spreadsheet $spreadsheet): array
    {
        if (! isset($this->tabs[$spreadsheet->google_id])) {
            $this->tabs[$spreadsheet->google_id] = $this->reader->tabs($spreadsheet->google_id);

            $spreadsheet->forceFill([
                'google_tabs' => array_values($this->tabs[$spreadsheet->google_id]),
                'tabs_fetched_at' => now(),
            ])->save();
        }

        return $this->tabs[$spreadsheet->google_id];
    }
}
