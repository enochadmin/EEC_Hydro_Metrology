<?php

namespace App\Http\Requests\Excel;

use Illuminate\Foundation\Http\FormRequest;

class StoreExcelUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('upload data') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:30720', 'extensions:xlsx,xls,csv'],
            'parameter_code' => ['nullable', 'string', 'max:60', 'exists:parameters,code'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.extensions' => 'Upload an .xlsx, .xls or .csv workbook.',
            'file.max' => 'Workbooks must be 30 MB or smaller.',
            'parameter_code.exists' => 'Choose a parameter from the catalogue.',
        ];
    }
}
