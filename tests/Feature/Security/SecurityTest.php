<?php

use App\Models\User;
use Database\Seeders\MeasurementUnitSeeder;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MeasurementUnitSeeder::class);
    $this->seed(ParameterSeeder::class);
});

it('refuses to serve a protected page to a guest', function (string $uri) {
    $response = $this->get($uri);

    expect($response->status())->not->toBe(200);
    $response->assertRedirect();
})->with([
    '/dashboard',
    '/basins',
    '/gis',
    '/excel',
    '/reports',
    '/users',
    '/access-control/roles',
    '/settings/system',
    '/activity-logs',
]);

it('forbids a viewer from administrative routes', function (string $uri) {
    $viewer = actorWithRole('Viewer');

    $this->actingAs($viewer)->get($uri)->assertForbidden();
})->with([
    '/users',
    '/settings/system',
    '/activity-logs',
    '/access-control/roles',
]);

it('forbids a viewer from editing another account', function () {
    $viewer = actorWithRole('Viewer');
    $other = User::factory()->create();

    $this->actingAs($viewer)->get("/users/{$other->id}/edit")->assertForbidden();
});

it('refuses privilege escalation through the role endpoint', function () {
    $analyst = actorWithRole('Data Analyst');

    $this->actingAs($analyst)
        ->post('/access-control/roles', [
            'name' => 'Escalated',
            'permissions' => ['manage permissions'],
        ])
        ->assertForbidden();
});

it('never exposes password or token fields in an account payload', function () {
    $admin = actorWithRole('System Administrator');
    $target = User::factory()->create();

    $response = $this->actingAs($admin)->get("/users/{$target->id}");

    $response->assertOk();

    $payload = $response->getContent();

    expect($payload)->not->toContain('remember_token')
        ->and($payload)->not->toContain($target->getAuthPassword());
});

it('treats hostile search input as data rather than SQL', function () {
    $viewer = actorWithRole('Viewer');

    $this->actingAs($viewer)
        ->get('/basins?search='.urlencode("' OR 1=1 --"))
        ->assertOk();
});

it('rejects an executable uploaded as a workbook', function () {
    $admin = actorWithRole(config('access.super_admin_role'));

    $file = UploadedFile::fake()->createWithContent('payload.php', '<?php echo "x";');

    $this->actingAs($admin)
        ->post('/excel/upload', ['file' => $file])
        ->assertSessionHasErrors('file');
});

it('keeps CSRF protection on state-changing routes', function () {
    $route = Route::getRoutes()->getByName('users.status');

    expect($route)->not->toBeNull()
        ->and($route->gatherMiddleware())->toContain('web')
        ->and(Route::getMiddlewareGroups()['web'])->toContain(ValidateCsrfToken::class);
});

it('denies a deactivated account any authenticated request', function () {
    $user = actorWithRole('Viewer');
    $user->forceFill(['is_active' => false, 'deactivated_at' => now()])->save();

    $response = $this->actingAs($user->fresh())->get('/dashboard');

    expect($response->status())->not->toBe(200);
});
