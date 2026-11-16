<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Filters for the analysis screen, its JSON refresh and its exports.
 *
 * A range is optional: an empty range means "everything stored". When both ends
 * are supplied the start must not be after the end, which the rule below
 * enforces rather than silently returning an empty series.
 */
class AnalysisFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view reports') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parameter_id' => ['nullable', 'integer', 'exists:parameters,id'],
            'station_id' => ['nullable', 'integer', 'exists:monitoring_stations,id'],
            'basin_id' => ['nullable', 'integer', 'exists:basins,id'],
            'sub_basin_id' => ['nullable', 'integer', 'exists:sub_basins,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'granularity' => ['nullable', 'in:daily,monthly,annual'],
            'quality' => ['nullable', 'in:approved,all'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $from = $this->input('from');
            $to = $this->input('to');

            if ($from && $to && strtotime((string) $from) > strtotime((string) $to)) {
                $validator->errors()->add('from', 'The start of the range must not be after its end.');
            }

            if ($this->input('quality') === 'all' && ! $this->user()?->can('generate reports')) {
                $validator->errors()->add('quality', 'Provisional records require the generate reports permission.');
            }
        });
    }

    /**
     * Validated filters with explicit defaults.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return [
            'parameter_id' => (int) $this->input('parameter_id'),
            'station_id' => $this->filled('station_id') ? (int) $this->input('station_id') : null,
            'basin_id' => $this->filled('basin_id') ? (int) $this->input('basin_id') : null,
            'sub_basin_id' => $this->filled('sub_basin_id') ? (int) $this->input('sub_basin_id') : null,
            'from' => $this->input('from') ?: null,
            'to' => $this->input('to') ?: null,
            'granularity' => $this->input('granularity') ?: 'monthly',
            'quality' => $this->input('quality') ?: 'approved',
        ];
    }
}
