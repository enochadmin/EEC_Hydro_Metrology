<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\MeasurementUnit;
use App\Models\Parameter;
use App\Models\StationCategory;
use App\Services\Audit\ActivityLogger;
use App\Services\Settings\SystemSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * System administration screen.
 *
 * Application settings, the parameter catalogue, measurement units and station
 * categories live on one page because they are edited by the same person in the
 * same sitting. Every write is authorised by the `manage settings` permission
 * and recorded in the audit trail.
 */
class SystemSettingsController extends Controller
{
    public function __construct(
        private readonly SystemSettingService $settings,
        private readonly ActivityLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('manage settings'), 403);

        return Inertia::render('Settings/System', [
            'groups' => $this->settings->registry(),
            'values' => collect($this->settings->all())->map(fn (array $definition) => [
                'key' => $definition['key'],
                'value' => $definition['value'],
                'is_default' => $definition['is_default'],
            ])->values()->all(),
            'parameters' => Parameter::query()
                ->with('defaultUnit:id,symbol')
                ->withCount('observations')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Parameter $parameter) => [
                    'id' => $parameter->id,
                    'code' => $parameter->code,
                    'name' => $parameter->name,
                    'symbol' => $parameter->symbol,
                    'category' => $parameter->category,
                    'value_type' => $parameter->value_type,
                    'decimals' => $parameter->decimals,
                    'default_unit_id' => $parameter->default_unit_id,
                    'unit' => $parameter->defaultUnit?->symbol,
                    'min_expected' => $parameter->min_expected === null ? null : (float) $parameter->min_expected,
                    'max_expected' => $parameter->max_expected === null ? null : (float) $parameter->max_expected,
                    'description' => $parameter->description,
                    'is_active' => (bool) $parameter->is_active,
                    'observations' => $parameter->observations_count,
                ]),
            'units' => MeasurementUnit::query()->orderBy('code')->get(['id', 'code', 'name', 'symbol', 'dimension', 'decimals', 'is_active']),
            'categories' => StationCategory::query()->orderBy('sort_order')->orderBy('label')->get(),
            'valueTypes' => ['instantaneous', 'daily_mean', 'daily_total', 'monthly_total', 'annual_total'],
            'parameterCategories' => ['meteorological', 'hydrological', 'water_quality', 'other'],
            'unitDimensions' => ['length', 'temperature', 'ratio', 'speed', 'time', 'volumetric_flow', 'other'],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage settings'), 403);

        $submitted = $request->input('settings', []);
        $rules = [];

        foreach ($this->settings->all() as $key => $definition) {
            if (! array_key_exists($key, $submitted)) {
                continue;
            }

            $rules['settings.'.$key] = $definition['rules'] ?? ['nullable', 'string', 'max:255'];
        }

        $validated = $request->validate($rules);

        $this->settings->put($validated['settings'] ?? [], $request->user());

        return back()->with('success', 'Settings saved.');
    }

    public function storeParameter(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage settings'), 403);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:30', 'alpha_dash', 'unique:parameters,code'],
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', 'in:meteorological,hydrological,water_quality,other'],
            'value_type' => ['required', 'in:instantaneous,daily_mean,daily_total,monthly_total,annual_total'],
            'default_unit_id' => ['nullable', 'exists:measurement_units,id'],
            'decimals' => ['required', 'integer', 'between:0,6'],
            'min_expected' => ['nullable', 'numeric'],
            'max_expected' => ['nullable', 'numeric', 'gte:min_expected'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $parameter = Parameter::create([
            ...$validated,
            'is_active' => true,
            'sort_order' => (int) Parameter::max('sort_order') + 1,
        ]);

        $this->audit->log('parameter.created', $parameter, "Parameter {$parameter->name} created", [
            'code' => $parameter->code,
            'value_type' => $parameter->value_type,
        ]);

        return back()->with('success', 'Parameter created.');
    }

    public function updateParameter(Request $request, Parameter $parameter): RedirectResponse
    {
        abort_unless($request->user()->can('manage settings'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', 'in:meteorological,hydrological,water_quality,other'],
            // Changing how existing readings aggregate would silently rewrite history.
            'value_type' => ['required', Rule::in([$parameter->value_type])],
            'default_unit_id' => ['nullable', 'exists:measurement_units,id'],
            'decimals' => ['required', 'integer', 'between:0,6'],
            'min_expected' => ['nullable', 'numeric'],
            'max_expected' => ['nullable', 'numeric', 'gte:min_expected'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $before = $parameter->only(['name', 'decimals', 'min_expected', 'max_expected', 'is_active']);

        $parameter->update($validated);

        $this->audit->log('parameter.updated', $parameter, "Parameter {$parameter->name} updated", [
            'before' => $before,
            'after' => $parameter->only(['name', 'decimals', 'min_expected', 'max_expected', 'is_active']),
        ]);

        return back()->with('success', 'Parameter updated.');
    }

    public function destroyParameter(Request $request, Parameter $parameter): RedirectResponse
    {
        abort_unless($request->user()->can('manage settings'), 403);

        if ($parameter->observations()->exists()) {
            throw ValidationException::withMessages([
                'parameter' => 'This parameter has observations and cannot be deleted. Deactivate it instead.',
            ]);
        }

        $name = $parameter->name;
        $parameter->delete();

        $this->audit->log('parameter.deleted', null, "Parameter {$name} deleted", ['name' => $name]);

        return back()->with('success', 'Parameter deleted.');
    }

    public function storeUnit(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('manage settings'), 403);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20', 'alpha_dash', 'unique:measurement_units,code'],
            'name' => ['required', 'string', 'max:80'],
            'symbol' => ['required', 'string', 'max:20'],
            'dimension' => ['required', 'in:length,temperature,ratio,speed,time,volumetric_flow,other'],
            'decimals' => ['required', 'integer', 'between:0,6'],
        ]);

        $unit = MeasurementUnit::create([...$validated, 'is_active' => true]);

        $this->audit->log('unit.created', null, "Measurement unit {$unit->code} created", ['code' => $unit->code]);

        return back()->with('success', 'Measurement unit created.');
    }

    public function updateUnit(Request $request, MeasurementUnit $unit): RedirectResponse
    {
        abort_unless($request->user()->can('manage settings'), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'symbol' => ['required', 'string', 'max:20'],
            'dimension' => ['required', 'in:length,temperature,ratio,speed,time,volumetric_flow,other'],
            'decimals' => ['required', 'integer', 'between:0,6'],
            'is_active' => ['boolean'],
        ]);

        $unit->update($validated);

        $this->audit->log('unit.updated', null, "Measurement unit {$unit->code} updated", ['code' => $unit->code]);

        return back()->with('success', 'Measurement unit updated.');
    }

    public function destroyUnit(Request $request, MeasurementUnit $unit): RedirectResponse
    {
        abort_unless($request->user()->can('manage settings'), 403);

        $inUse = Parameter::where('default_unit_id', $unit->id)->exists()
            || \App\Models\HydrologicalObservation::where('measurement_unit_id', $unit->id)->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'unit' => 'This unit is referenced by parameters or observations and cannot be deleted.',
            ]);
        }

        $code = $unit->code;
        $unit->delete();

        $this->audit->log('unit.deleted', null, "Measurement unit {$code} deleted", ['code' => $code]);

        return back()->with('success', 'Measurement unit deleted.');
    }

    public function updateCategory(Request $request, StationCategory $category): RedirectResponse
    {
        abort_unless($request->user()->can('manage settings'), 403);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:300'],
            'colour' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,999'],
        ]);

        $category->update($validated);

        $this->audit->log('station_category.updated', null, "Station category {$category->code} updated", [
            'code' => $category->code,
            'label' => $category->label,
        ]);

        return back()->with('success', 'Station category updated.');
    }
}
