<?php

namespace App\Http\Requests\Stations;

use App\Models\MonitoringStation;
use Illuminate\Foundation\Http\FormRequest;

class StoreStationDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $station = $this->route('station');

        return $station instanceof MonitoringStation
            && ($this->user()?->can('manageDocuments', $station) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('title')) && trim($this->input('title')) === '') {
            $this->merge(['title' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:180'],
            'file' => ['required', 'file', 'max:10240', 'extensions:pdf,doc,docx,xls,xlsx,csv,txt,geojson,zip,png,jpg,jpeg'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.extensions' => 'Allowed attachments: PDF, Word, Excel, CSV, TXT, GeoJSON, ZIP, PNG or JPEG.',
            'file.max' => 'Attachments must be 10 MB or smaller.',
        ];
    }
}
