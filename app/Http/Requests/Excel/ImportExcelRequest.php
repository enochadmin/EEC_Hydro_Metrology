<?php

namespace App\Http\Requests\Excel;

use Illuminate\Foundation\Http\FormRequest;

class ImportExcelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('approve data') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'station_map' => ['required', 'array'],
            'station_map.*.mode' => ['required', 'in:existing,create'],
            'station_map.*.station_id' => ['nullable', 'integer', 'exists:monitoring_stations,id'],
            'station_map.*.code' => ['nullable', 'string', 'max:40'],
            'station_map.*.basin_id' => ['nullable', 'integer', 'exists:basins,id'],
            'station_map.*.sub_basin_id' => ['nullable', 'integer', 'exists:sub_basins,id'],
        ];
    }
}
