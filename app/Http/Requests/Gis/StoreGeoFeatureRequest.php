<?php

namespace App\Http\Requests\Gis;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGeoFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage geographic boundaries') ?? false;
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'code', 'source_note'] as $field) {
            if (is_string($this->input($field)) && trim($this->input($field)) === '') {
                $this->merge([$field => null]);
            }
        }

        if ($this->input('basin_id') === '') {
            $this->merge(['basin_id' => null]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'layer_type' => ['required', Rule::in(['national_boundary', 'river', 'lake'])],
            'name' => ['nullable', 'string', 'max:180'],
            'code' => ['nullable', 'string', 'max:80'],
            'basin_id' => ['nullable', 'integer', Rule::exists('basins', 'id')->whereNull('deleted_at')],
            'source_note' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', 'file', 'max:20480', 'extensions:geojson,json,txt'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'layer_type.required' => 'Choose which map layer this file belongs to.',
            'file.extensions' => 'Upload a .geojson, .json or .txt file.',
            'file.max' => 'Geographic files must be 20 MB or smaller.',
            'basin_id.exists' => 'The selected parent basin no longer exists.',
        ];
    }

    /**
     * Shapefile/KML remain out of scope until the GDAL conversion service is
     * enabled. Say so explicitly instead of returning a generic extension error.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $extension = strtolower((string) $this->file('file')?->getClientOriginalExtension());

            if (in_array($extension, ['shp', 'zip', 'kml', 'kmz'], true)) {
                $validator->errors()->add(
                    'file',
                    'Shapefile and KML import requires the GDAL conversion step, which is not enabled yet. Export the layer as GeoJSON and upload that file.',
                );
            }
        });
    }
}
