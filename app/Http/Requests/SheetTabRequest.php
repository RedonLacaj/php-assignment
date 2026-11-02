<?php

namespace App\Http\Requests;

use App\Models\Spreadsheet;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SheetTabRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // store: spreadsheets/{spreadsheet}/tabs, update: tabs/{tab}
        $tab = $this->route('tab');
        $spreadsheet = $tab?->spreadsheet ?? $this->route('spreadsheet');

        return [
            'name' => ['bail', 'required', 'string', 'max:255',
                Rule::unique('sheet_tabs')->where('spreadsheet_id', $spreadsheet->id)->ignore($tab),
                $this->existsInFile($spreadsheet, $tab?->name),
            ],
            'header_row' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Choose a tab of the spreadsheet.',
            'name.unique' => 'This tab is already added to the spreadsheet.',
            'header_row.min' => 'The header row starts at 1.',
        ];
    }

    public function tabData(): array
    {
        return $this->only('name', 'header_row') + ['is_active' => $this->boolean('is_active')];
    }

    // Only tabs of the Google file can be added, checked against the fetched tab names
    private function existsInFile(Spreadsheet $spreadsheet, ?string $currentName): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($spreadsheet, $currentName) {
            if ($value === $currentName) {
                return;
            }

            if ($spreadsheet->google_tabs === null) {
                $fail('Fetch the tab names of the spreadsheet first.');
            } elseif (! in_array($value, $spreadsheet->google_tabs, true)) {
                $fail("There is no tab \"{$value}\" in the spreadsheet. Fetch the tab names again if it was just added.");
            }
        };
    }
}
