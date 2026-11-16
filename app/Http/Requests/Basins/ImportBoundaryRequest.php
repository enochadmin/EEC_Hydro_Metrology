<?php

namespace App\Http\Requests\Basins;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the uploaded GeoJSON envelope. Geometry itself is validated by
 * GeoJsonConverter, which produces precise messages for malformed rings,
 * out-of-range coordinates and unsupported geometry types.
 */
class ImportBoundaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = $this->route('basin') ?? $this->route('subBasin');

        return $subject !== null
            && $this->user() !== null
            && $this->user()->can('importBoundary', $subject);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480', 'extensions:geojson,json,txt'],
            'name' => ['required', 'string', 'max:150'],
            'is_primary' => ['sometimes', 'boolean'],
            'accuracy_m' => ['nullable', 'numeric', 'between:0,100000'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.extensions' => 'Upload a .geojson, .json or .txt file containing a single polygon.',
            'file.max' => 'Boundary files must be 20 MB or smaller.',
            'name.required' => 'Give the boundary version a name, for example "EEC master boundary 2026".',
        ];
    }
}
