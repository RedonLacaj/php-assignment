<?php

namespace App\Services;

interface SheetReader
{
    /**
     * Returns all values of a tab as a list of rows (each row is a list of cells).
     */
    public function rows(string $spreadsheetId, string $tab): array;

    /**
     * Tabs of the spreadsheet in the order they appear in the file, as [tab id => tab name].
     */
    public function tabs(string $spreadsheetId): array;

    /**
     * Title of the spreadsheet as shown in Google Sheets.
     */
    public function title(string $spreadsheetId): string;
}
