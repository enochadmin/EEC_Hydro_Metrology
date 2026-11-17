<?php

namespace App\Http\Requests\Stations;

use App\Models\MonitoringStation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class MonitoringStationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        foreach (['code', 'name'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim((string) $this->input($field))]);
            }
        }

        foreach (['name_alt', 'operator', 'notes', 'data_source_id', 'sub_basin_id', 'elevation_m', 'latitude', 'longitude', 'established_on'] as $field) {
            if (is_string($this->input($field)) && trim((string) $this->input($field)) === '') {
                $this->merge([$field => null]);
            }
        }

        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper((string) $this->input('code'))]);
        }

        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }
    }

    public function rules(): array
    {
        $station = $this->route('station');
        $ignoreId = $station instanceof MonitoringStation ? $station->getKey() : null;
        $basinId = $this->input('basin_id');
        $existingParameterIds = $station instanceof MonitoringStation
            ? $station->parameters()->pluck('parameters.id')->all()
            : [];

        return [
            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Z0-9][A-Z0-9._\/-]*$/',
                Rule::unique('monitoring_stations', 'code')->ignore($ignoreId),
            ],
            'name' => ['required', 'string', 'max:150'],
            'name_alt' => ['nullable', 'string', 'max:150'],
            'station_type' => [
                'required',
                'string',
                Rule::exists('station_categories', 'code')->where(function ($query) use ($station): void {
                    $query->where('is_active', true);

                    if ($station instanceof MonitoringStation) {
                        $query->orWhere('code', $station->station_type);
                    }
                }),
            ],
            'basin_id' => [
                'required',
                'integer',
                Rule::exists('basins', 'id')->where(function ($query) use ($station): void {
                    $query->where('is_active', true)->whereNull('deleted_at');

                    if ($station instanceof MonitoringStation) {
                        $query->orWhere('id', $station->basin_id);
                    }
                }),
            ],
            'sub_basin_id' => [
                'nullable',
                'integer',
                Rule::exists('sub_basins', 'id')->where(function ($query) use ($basinId, $station): void {
                    $query->where('basin_id', $basinId)
                        ->where(function ($query) use ($station): void {
                            $query->where('is_active', true)
                                ->whereNull('deleted_at');

                            if ($station instanceof MonitoringStation) {
                                $query->orWhere('id', $station->sub_basin_id);
                            }
                        });
                }),
            ],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'elevation_m' => ['nullable', 'numeric', 'between:-500,9000'],
            'established_on' => ['nullable', 'date'],
            'operator' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'data_source_id' => [
                'nullable',
                'integer',
                Rule::exists('data_sources', 'id')->where(function ($query) use ($station): void {
                    $query->where('is_active', true);

                    if ($station instanceof MonitoringStation) {
                        $query->orWhere('id', $station->data_source_id);
                    }
                }),
            ],
            'data_status' => ['required', 'in:pending,provisional,verified'],
            'is_active' => ['required', 'boolean'],
            'parameters' => ['required', 'array'],
            'parameters.*' => [
                'integer',
                'distinct',
                Rule::exists('parameters', 'id')->where(function ($query) use ($existingParameterIds): void {
                    $query->where('is_active', true);

                    if ($existingParameterIds !== []) {
                        $query->orWhereIn('id', $existingParameterIds);
                    }
                }),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('data_status') === 'verified') {
                foreach (['latitude' => 'latitude', 'longitude' => 'longitude'] as $field => $label) {
                    if ($this->input($field) === null || $this->input($field) === '') {
                        $validator->errors()->add(
                            $field,
                            'A verified station needs confirmed coordinates. Enter '.$label.' or keep the station pending or provisional.',
                        );
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'code.regex' => 'Use letters, numbers, dots, dashes or slashes only.',
            'code.unique' => 'This station code is already registered, including archived stations.',
            'station_type.exists' => 'Choose an active station category.',
            'sub_basin_id.exists' => 'Choose a sub-basin that belongs to the selected basin.',
            'latitude.between' => 'Latitude must be between -90 and 90 degrees (WGS84).',
            'longitude.between' => 'Longitude must be between -180 and 180 degrees (WGS84).',
            'latitude.required_with' => 'Enter both latitude and longitude, or leave both empty.',
            'longitude.required_with' => 'Enter both longitude and latitude, or leave both empty.',
            'elevation_m.between' => 'Elevation must be between -500 m and 9,000 m.',
            'parameters.*.exists' => 'Choose active parameters from the catalogue.',
        ];
    }

    public function attributes(): array
    {
        return [
            'station_type' => 'station category',
            'basin_id' => 'basin',
            'sub_basin_id' => 'sub-basin',
            'elevation_m' => 'elevation',
            'established_on' => 'installation date',
            'data_source_id' => 'data source',
            'is_active' => 'operational status',
        ];
    }
}
