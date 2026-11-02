<?php

namespace Tests\Fakes;

use App\Services\SheetReader;

class FakeSheetReader implements SheetReader
{
    // [spreadsheet id => [tab id => ['title' => ..., 'rows' => [...]]]]
    private array $sheets = [];

    public function set(string $spreadsheetId, string $tab, array $rows): void
    {
        foreach ($this->sheets[$spreadsheetId] ?? [] as $id => $sheet) {
            if ($sheet['title'] === $tab) {
                $this->sheets[$spreadsheetId][$id]['rows'] = $rows;

                return;
            }
        }

        // like in Google, the first tab gets id 0
        $id = isset($this->sheets[$spreadsheetId]) ? 1000 + count($this->sheets[$spreadsheetId]) : 0;
        $this->sheets[$spreadsheetId][$id] = ['title' => $tab, 'rows' => $rows];
    }

    public function rename(string $spreadsheetId, string $from, string $to): void
    {
        foreach ($this->sheets[$spreadsheetId] as $id => $sheet) {
            if ($sheet['title'] === $from) {
                $this->sheets[$spreadsheetId][$id]['title'] = $to;
            }
        }
    }

    public function rows(string $spreadsheetId, string $tab): array
    {
        foreach ($this->sheets[$spreadsheetId] ?? [] as $sheet) {
            if ($sheet['title'] === $tab) {
                return $sheet['rows'];
            }
        }

        throw new \RuntimeException("Unable to parse range: {$tab}");
    }

    public function title(string $spreadsheetId): string
    {
        if (! isset($this->sheets[$spreadsheetId])) {
            throw new \RuntimeException('Spreadsheet not found, check the URL or ID.');
        }

        return "Spreadsheet {$spreadsheetId}";
    }

    public function tabs(string $spreadsheetId): array
    {
        return array_map(fn ($sheet) => $sheet['title'], $this->sheets[$spreadsheetId] ?? []);
    }
}
