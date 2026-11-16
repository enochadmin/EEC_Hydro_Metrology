<?php

use App\Models\Basin;
use App\Models\SubBasin;
use Database\Seeders\RolePermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('renders the basin create and edit pages for a basin manager', function () {
    $basin = Basin::factory()->create();
    $manager = actorWithRole('Basin Manager');

    $this->actingAs($manager)
        ->get('/basins/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Basins/Create')
            ->has('statuses', 3)
            ->has('dataSources', 0)
        );

    $this->actingAs($manager)
        ->get("/basins/{$basin->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Basins/Edit')
            ->where('basin.id', $basin->id)
            ->where('basin.data_status', 'pending')
        );
});

it('renders the sub-basin create and edit pages with parent options', function () {
    $basin = Basin::factory()->create(['code' => 'BH-01']);
    $subBasin = SubBasin::factory()->create(['basin_id' => $basin->id]);
    $manager = actorWithRole('Basin Manager');

    $this->actingAs($manager)
        ->get('/sub-basins/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SubBasins/Create')
            ->has('basins', 1)
            ->where('defaultBasinId', null)
        );

    $this->actingAs($manager)
        ->get("/sub-basins/create?basin_id={$basin->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('defaultBasinId', $basin->id));

    $this->actingAs($manager)
        ->get("/sub-basins/{$subBasin->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('SubBasins/Edit')
            ->where('subBasin.id', $subBasin->id)
        );
});

it('returns 404 when the preselected parent basin does not exist', function () {
    $this->actingAs(actorWithRole('Basin Manager'))
        ->get('/sub-basins/create?basin_id=999999')
        ->assertNotFound();
});
