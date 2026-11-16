<?php

namespace App\Http\Requests\Basins;

use App\Models\SubBasin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared validation for sub-basin create and update.
 *
 * `basin_id` is the geographic parent and is required: a sub-basin without a
 * basin would break the hierarchy the GIS module depends on.
 */
abstract class SubBasinRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $code = $this->input('code');

        $this->merge([
            'code' => is_string($code) ? strtoupper(trim($code)) : $code,
            'name' => is_string($this->input('name')) ? trim((string) $this->input('name')) : $this->input('name'),
            'name_alt' => $this->nullIfBlank('name_alt'),
            'description' => $this->nullIfBlank('description'),
            'source_note' => $this->nullIfBlank('source_note'),
            'data_source_id' => $this->nullIfBlank('data_source_id'),
        ]);

        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }

        foreach (['area_km2', 'centroid_lat', 'centroid_lng', 'elevation_m'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $subBasin = $this->route('subBasin');
        $ignore = $subBasin instanceof SubBasin ? $subBasin->getKey() : (is_numeric($subBasin) ? (int) $subBasin : null);

        return [
            'basin_id' => ['required', 'integer', Rule::exists('basins', 'id')->whereNull('deleted_at')],
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9][A-Z0-9._\/-]*$/',
                Rule::unique('sub_basins', 'code')->ignore($ignore),
            ],
            'name' => ['required', 'string', 'max:150'],
            'name_alt' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'area_km2' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'centroid_lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:centroid_lng'],
            'centroid_lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:centroid_lat'],
            'elevation_m' => ['nullable', 'numeric', 'between:-500,9000'],
            'data_source_id' => ['nullable', 'integer', Rule::exists('data_sources', 'id')],
            'data_status' => ['required', 'in:pending,provisional,verified'],
            'source_note' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('data_status') !== 'verified') {
                return;
            }

            foreach (['area_km2' => 'geographic area', 'centroid_lat' => 'latitude', 'centroid_lng' => 'longitude'] as $field => $label) {
                if ($this->input($field) === null || $this->input($field) === '') {
                    $validator->errors()->add(
                        $field,
                        'A verified sub-basin needs a confirmed '.$label.'. Enter it before changing the status, or keep the record as provisional.',
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'basin_id.required' => 'Choose the parent basin for this sub-basin.',
            'basin_id.exists' => 'The selected parent basin no longer exists.',
            'code.regex' => 'Use letters, numbers, dots, dashes or slashes only. The code is stored in upper case.',
            'code.unique' => 'This sub-basin code is already registered, including archived sub-basins.',
            'centroid_lat.between' => 'Latitude must be between -90 and 90 degrees (WGS84).',
            'centroid_lng.between' => 'Longitude must be between -180 and 180 degrees (WGS84).',
            'centroid_lat.required_with' => 'Enter both latitude and longitude, or leave both empty.',
            'centroid_lng.required_with' => 'Enter both latitude and longitude, or leave both empty.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'basin_id' => 'parent basin',
            'name_alt' => 'alternative name',
            'area_km2' => 'geographic area',
            'centroid_lat' => 'latitude',
            'centroid_lng' => 'longitude',
            'elevation_m' => 'mean elevation',
            'data_source_id' => 'data source',
        ];
    }

    private function nullIfBlank(string $key): mixed
    {
        $value = $this->input($key);

        return is_string($value) && trim($value) === '' ? null : $value;
    }
}
