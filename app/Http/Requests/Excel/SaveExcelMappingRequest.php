<?php

namespace App\Http\Requests\Excel;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveExcelMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('transform data') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'sheet_name' => ['required', 'string', 'max:150'],
            'structure_type' => ['required', Rule::in([
                'monthly_daily_columns',
                'multi_header_matrix',
                'daily_series',
            ])],
            'mapping' => ['required', 'array'],
            'mapping.station_name' => ['nullable', 'string', 'max:180'],
            'mapping.parameter_code' => ['nullable', 'string', 'max:80'],
            'mapping.time' => ['nullable', 'string', 'max:10'],
        ];
    }
}
