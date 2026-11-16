<?php

use App\Support\Gis\BasemapRegistry;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    Cache::forget(BasemapRegistry::PROBE_CACHE_KEY);
});

it('offers keyless world basemaps when no key is configured', function () {
    config()->set('gis.basemap_keys.carto', null);

    $registry = app(BasemapRegistry::class);
    $basemaps = $registry->all();

    expect(collect($basemaps)->pluck('id'))
        ->toContain('terrain')
        ->toContain('imagery')
        ->toContain('streets')
        ->not->toContain('light')
        ->not->toContain('dark');

    expect($basemaps[0]['url'])->toContain('World_Topo_Map');
    expect($basemaps[0]['attribution'])->not->toBeEmpty();
    expect($registry->defaultId())->toBe('terrain');
    expect($registry->status()['state'])->toBe('missing');
});

it('appends the configured key to keyed styles', function () {
    config()->set('gis.basemap_keys.carto', 'demo-key-123');

    $light = collect(app(BasemapRegistry::class)->all())->firstWhere('id', 'light');

    expect($light)->not->toBeNull();
    expect($light['url'])->toContain('?key=demo-key-123');
    expect(app(BasemapRegistry::class)->status()['state'])->toBe('ok');
});

it('hides keyed styles once the provider has rejected the key', function () {
    config()->set('gis.basemap_keys.carto', 'demo-key-123');

    Cache::put(
        BasemapRegistry::PROBE_CACHE_KEY,
        ['state' => 'invalid', 'checked_at' => now()->toIso8601String()],
        now()->addHour(),
    );

    $registry = app(BasemapRegistry::class);

    expect(collect($registry->all())->pluck('id'))->not->toContain('light');
    expect($registry->status()['state'])->toBe('invalid');
    expect($registry->status()['message'])->toContain('rejected');
});

it('falls back to the first available style when the configured default is hidden', function () {
    config()->set('gis.basemap_keys.carto', null);
    config()->set('gis.default_basemap', 'dark');

    expect(app(BasemapRegistry::class)->defaultId())->toBe('terrain');
});

it('ships the basemap catalogue with the map page', function () {
    $this->actingAs(actorWithRole('Viewer'))
        ->get('/gis')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Gis/Index')
            ->has('basemaps', 3)
            ->where('default_basemap', 'terrain')
            ->where('basemap_status.state', 'missing'));
});

it('passes a basemap to the dashboard mini map', function () {
    $this->actingAs(actorWithRole('Viewer'))
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('basemap.id', 'terrain'));
});
