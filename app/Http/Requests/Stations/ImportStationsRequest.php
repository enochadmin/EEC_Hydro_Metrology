<?php

namespace App\Http\Requests\Stations;

use App\Models\MonitoringStation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportStationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MonitoringStation::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480', 'extensions:xlsx,xls,csv'],
            'code_prefix' => ['nullable', 'string', 'max:10', 'regex:/^[A-Za-z0-9]+$/'],
            'utm_zone' => ['nullable', 'integer', 'between:1,60'],
            'coordinates' => ['nullable', Rule::in(['auto', 'projected', 'degrees'])],
            'basin_id' => ['nullable', 'integer', Rule::exists('basins', 'id')->whereNull('deleted_at')],
            'sub_basin_id' => ['nullable', 'integer', Rule::exists('sub_basins', 'id')->whereNull('deleted_at')],
            'data_source_id' => ['nullable', 'integer', Rule::exists('data_sources', 'id')],
            'data_status' => ['nullable', Rule::in(['pending', 'provisional', 'verified'])],
            'parameters' => ['nullable', 'array'],
            'parameters.*' => ['string', Rule::exists('parameters', 'code')->where('is_active', true)],
            'operator' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.extensions' => 'Upload an .xlsx, .xls or .csv station list.',
            'file.max' => 'Station lists must be 20 MB or smaller.',
            'code_prefix.regex' => 'Use letters and numbers only for the code prefix.',
            'coordinates.in' => 'Choose automatic detection, projected metres or decimal degrees.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        $validated = $this->validated();

        return [
            'code_prefix' => $validated['code_prefix'] ?? null,
            'utm_zone' => $validated['utm_zone'] ?? null,
            'coordinates' => $validated['coordinates'] ?? 'auto',
            'basin_id' => $validated['basin_id'] ?? null,
            'sub_basin_id' => $validated['sub_basin_id'] ?? null,
            'data_source_id' => $validated['data_source_id'] ?? null,
            'data_status' => $validated['data_status'] ?? null,
            'parameters' => $validated['parameters'] ?? [],
            'operator' => $validated['operator'] ?? null,
        ];
    }
}
