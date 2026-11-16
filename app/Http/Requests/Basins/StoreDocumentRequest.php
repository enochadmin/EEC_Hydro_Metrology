<?php

namespace App\Http\Requests\Basins;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = $this->route('basin') ?? $this->route('subBasin');

        return $subject !== null
            && $this->user() !== null
            && $this->user()->can('manageDocuments', $subject);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('title')) && trim($this->input('title')) === '') {
            $this->merge(['title' => null]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:180'],
            'file' => ['required', 'file', 'max:10240', 'extensions:pdf,doc,docx,xls,xlsx,csv,txt,geojson,zip,png,jpg,jpeg'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.extensions' => 'Allowed attachments: PDF, Word, Excel, CSV, TXT, GeoJSON, ZIP, PNG or JPEG.',
            'file.max' => 'Attachments must be 10 MB or smaller.',
        ];
    }
}
