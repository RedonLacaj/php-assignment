<?php

namespace App\Http\Requests;

use App\Models\Spreadsheet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SpreadsheetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // the form accepts the full Google Sheets URL as well
        $this->merge(['google_id' => Spreadsheet::extractGoogleId((string) $this->input('google_id'))]);
    }

    public function rules(): array
    {
        $googleId = ['required', 'string', 'max:255'];

        // when adding, an existing file is handled by the controller (redirect to it)
        if ($spreadsheet = $this->route('spreadsheet')) {
            $googleId[] = Rule::unique('spreadsheets')->ignore($spreadsheet);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'google_id' => $googleId,
        ];
    }

    public function messages(): array
    {
        return [
            'google_id.required' => 'Paste the spreadsheet URL or ID.',
            'google_id.unique' => 'Another entry already uses this spreadsheet. Each file can be added only once.',
        ];
    }

    public function spreadsheetData(): array
    {
        return $this->only('name', 'google_id') + ['is_active' => $this->boolean('is_active')];
    }
}
