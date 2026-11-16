<?php

namespace App\Http\Requests\Basins;

use App\Models\Basin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared validation for basin create and update.
 *
 * Codes are normalised to upper case before validation so `bh-01` and `BH-01`
 * cannot become two records. The unique rule intentionally includes soft
 * deleted rows: an archived basin keeps its official code reserved.
 */
abstract class BasinRequest extends FormRequest
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
        $basin = $this->route('basin');
        $ignore = $basin instanceof Basin ? $basin->getKey() : (is_numeric($basin) ? (int) $basin : null);

        return [
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9][A-Z0-9._\/-]*$/',
                Rule::unique('basins', 'code')->ignore($ignore),
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

    /**
     * A "verified" record must actually carry verified geography. Without this
     * guard the status becomes a label with no evidence behind it.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('data_status') !== 'verified') {
                return;
            }

            $required = [
                'area_km2' => 'geographic area',
                'centroid_lat' => 'latitude',
                'centroid_lng' => 'longitude',
            ];

            foreach ($required as $field => $label) {
                if ($this->input($field) === null || $this->input($field) === '') {
                    $validator->errors()->add(
                        $field,
                        'A verified basin needs a confirmed '.$label.'. Enter it before changing the status, or keep the record as provisional.',
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
            'code.regex' => 'Use letters, numbers, dots, dashes or slashes only. The code is stored in upper case.',
            'code.unique' => 'This basin code is already registered, including archived basins. Choose a different code.',
            'centroid_lat.between' => 'Latitude must be between -90 and 90 degrees (WGS84).',
            'centroid_lng.between' => 'Longitude must be between -180 and 180 degrees (WGS84).',
            'centroid_lat.required_with' => 'Enter both latitude and longitude, or leave both empty.',
            'centroid_lng.required_with' => 'Enter both latitude and longitude, or leave both empty.',
            'elevation_m.between' => 'Mean elevation must be between -500 m and 9,000 m.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name_alt' => 'alternative name',
            'area_km2' => 'geographic area',
            'centroid_lat' => 'latitude',
            'centroid_lng' => 'longitude',
            'elevation_m' => 'mean elevation',
            'data_source_id' => 'data source',
            'data_status' => 'data status',
        ];
    }

    private function nullIfBlank(string $key): mixed
    {
        $value = $this->input($key);

        return is_string($value) && trim($value) === '' ? null : $value;
    }
}
