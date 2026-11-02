<?php

namespace App\Http\Requests;

use App\Models\ColumnMapping;
use App\Models\TableMapping;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TableMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $columns = collect($this->input('columns', []))
            ->filter(fn ($c) => filled($c['source_column'] ?? null) || filled($c['target_column'] ?? null))
            ->map(fn ($c) => [...$c, 'target_column' => strtolower(trim($c['target_column'] ?? ''))])
            ->values()
            ->all();

        $this->merge([
            'target_table' => strtolower(trim((string) $this->input('target_table'))),
            'columns' => $columns,
        ]);
    }

    public function rules(): array
    {
        $identifier = 'regex:/^[a-z][a-z0-9_]{0,63}$/';

        return [
            'target_table' => ['required', $identifier, Rule::notIn(config('sheets.protected_tables'))],
            'mode' => ['required', Rule::in(TableMapping::MODES)],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*.source_column' => ['required', 'string', 'max:255'],
            'columns.*.target_column' => ['required', $identifier, 'distinct', Rule::notIn(config('sheets.reserved_columns'))],
            'columns.*.type' => ['required', Rule::in(ColumnMapping::TYPES)],
        ];
    }

    public function messages(): array
    {
        return [
            'target_table.regex' => 'Table name may contain only lowercase letters, digits and underscores.',
            'columns.*.target_column.regex' => 'Column names may contain only lowercase letters, digits and underscores.',
            'columns.*.target_column.not_in' => 'This column name is used by the sync itself.',
            'columns.required' => 'Add at least one column.',
        ];
    }

    public function mappingData(): array
    {
        return $this->only('target_table', 'mode') + ['is_active' => $this->boolean('is_active')];
    }

    public function columnData(): array
    {
        return collect($this->validated('columns'))
            ->map(fn ($c) => [
                'source_column' => trim($c['source_column']),
                'target_column' => $c['target_column'],
                'type' => $c['type'],
            ])
            ->all();
    }
}
