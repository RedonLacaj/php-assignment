<?php

namespace App\Services;

use Google\Client;
use Google\Service\Exception as GoogleException;
use Google\Service\Sheets;
use RuntimeException;

class GoogleSheetReader implements SheetReader
{
    private ?Sheets $service = null;

    public function __construct(private string $credentialsPath) {}

    public function rows(string $spreadsheetId, string $tab): array
    {
        // Quote the tab name so names with spaces work
        $range = "'".str_replace("'", "''", $tab)."'";

        $response = $this->call(fn () => $this->service()->spreadsheets_values->get($spreadsheetId, $range, [
            'valueRenderOption' => 'UNFORMATTED_VALUE',
            'dateTimeRenderOption' => 'FORMATTED_STRING',
        ]));

        return $response->getValues() ?? [];
    }

    public function tabs(string $spreadsheetId): array
    {
        // So it returns only the properties that we need, like the tab id and name
        $sheet = $this->call(fn () => $this->service()->spreadsheets->get($spreadsheetId, ['fields' => 'sheets.properties(sheetId,title)']));

        $tabs = [];
        foreach ($sheet->getSheets() as $s) {
            $tabs[$s->getProperties()->getSheetId()] = $s->getProperties()->getTitle();
        }

        return $tabs;
    }

    public function title(string $spreadsheetId): string
    {
        $sheet = $this->call(fn () => $this->service()->spreadsheets->get($spreadsheetId, ['fields' => 'properties.title']));

        return $sheet->getProperties()->getTitle();
    }

    /**
     * Google returns errors as a JSON document, turn them into a message
     * that can be shown in the UI and the sync log.
     */
    private function call(callable $request): mixed
    {
        try {
            return $request();
        } catch (GoogleException $e) {
            $message = $e->getErrors()[0]['message'] ?? $e->getMessage();

            throw new RuntimeException(match (true) {
                $e->getCode() === 404 => 'Spreadsheet not found, check the URL or ID.',
                $e->getCode() === 403 && str_contains($message, 'does not have permission') => 'No access to the spreadsheet. Share it with '.($this->clientEmail() ?? 'the service account').' (Viewer is enough).',
                default => $message,
            }, $e->getCode(), $e);
        }
    }

    private function clientEmail(): ?string
    {
        $credentials = json_decode((string) @file_get_contents($this->credentialsPath), true);

        return $credentials['client_email'] ?? null;
    }

    private function service(): Sheets
    {
        if ($this->service) {
            return $this->service;
        }

        if (! is_file($this->credentialsPath)) {
            throw new RuntimeException("Google credentials file not found: {$this->credentialsPath}");
        }

        $client = new Client;
        $client->setApplicationName(config('app.name'));
        $client->setScopes([Sheets::SPREADSHEETS_READONLY]);
        $client->setAuthConfig($this->credentialsPath);

        return $this->service = new Sheets($client);
    }
}
