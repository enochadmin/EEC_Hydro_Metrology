<?php

use App\Http\Controllers\AccessControl\RoleController;
use App\Http\Controllers\Administration\ActivityLogController;
use App\Http\Controllers\Basins\BasinController;
use App\Http\Controllers\Basins\SubBasinController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Excel\ExcelExportController;
use App\Http\Controllers\Excel\ExcelImportController;
use App\Http\Controllers\Excel\ExcelPreviewController;
use App\Http\Controllers\Excel\ExcelTransformationController;
use App\Http\Controllers\Excel\ExcelValidationController;
use App\Http\Controllers\Gis\GisController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Reports\AnalysisReportController;
use App\Http\Controllers\Reports\BasinReportController;
use App\Http\Controllers\Reports\ReportController;
use App\Http\Controllers\Reports\StationReportController;
use App\Http\Controllers\Settings\AppearanceController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SystemSettingsController;
use App\Http\Controllers\Users\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', HomeController::class)->name('home');

Route::get('welcome', fn () => Inertia::render('Welcome'))->name('welcome');

Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)
        ->middleware('permission:view dashboard')
        ->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Account settings (any authenticated user)
    |--------------------------------------------------------------------------
    */

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('settings.profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('settings.profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('settings.profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('settings.password.edit');
    Route::put('settings/password', [PasswordController::class, 'update'])->name('settings.password.update');

    Route::get('settings/appearance', AppearanceController::class)->name('settings.appearance.edit');

    /*
    |--------------------------------------------------------------------------
    | User management
    |--------------------------------------------------------------------------
    | Route middleware blocks the request before the controller runs; the
    | controller repeats the check through UserPolicy (defence in depth).
    */

    Route::prefix('users')->name('users.')->group(function () {
        Route::get('/', [UserController::class, 'index'])
            ->middleware('permission:view users')
            ->name('index');

        Route::get('create', [UserController::class, 'create'])
            ->middleware('permission:create users')
            ->name('create');

        Route::post('/', [UserController::class, 'store'])
            ->middleware('permission:create users')
            ->name('store');

        Route::get('{user}/edit', [UserController::class, 'edit'])
            ->middleware('permission:edit users')
            ->name('edit');

        Route::patch('{user}', [UserController::class, 'update'])
            ->middleware('permission:edit users')
            ->name('update');

        Route::patch('{user}/status', [UserController::class, 'updateStatus'])
            ->middleware('permission:deactivate users')
            ->name('status');

        Route::post('{user}/password', [UserController::class, 'resetPassword'])
            ->middleware('permission:edit users')
            ->name('password');

        Route::get('{user}', [UserController::class, 'show'])
            ->middleware('permission:view users')
            ->name('show');
    });

    /*
    |--------------------------------------------------------------------------
    | Role and permission management
    |--------------------------------------------------------------------------
    */

    Route::prefix('access-control')->name('access.')->group(function () {
        Route::get('roles', [RoleController::class, 'index'])
            ->middleware('permission:manage permissions')
            ->name('roles.index');

        Route::get('roles/{role}/edit', [RoleController::class, 'edit'])
            ->middleware('permission:manage permissions')
            ->name('roles.edit');

        Route::post('roles', [RoleController::class, 'store'])
            ->middleware('permission:manage permissions')
            ->name('roles.store');

        Route::put('roles/{role}', [RoleController::class, 'update'])
            ->middleware('permission:manage permissions')
            ->name('roles.update');

        Route::delete('roles/{role}', [RoleController::class, 'destroy'])
            ->middleware('permission:manage permissions')
            ->name('roles.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | System administration
    |--------------------------------------------------------------------------
    */

    Route::prefix('settings/system')->name('settings.system.')->group(function () {
        Route::get('/', [SystemSettingsController::class, 'index'])
            ->middleware('permission:manage settings')
            ->name('index');

        Route::put('/', [SystemSettingsController::class, 'update'])
            ->middleware('permission:manage settings')
            ->name('update');

        Route::post('parameters', [SystemSettingsController::class, 'storeParameter'])
            ->middleware('permission:manage settings')
            ->name('parameters.store');

        Route::patch('parameters/{parameter}', [SystemSettingsController::class, 'updateParameter'])
            ->middleware('permission:manage settings')
            ->name('parameters.update');

        Route::delete('parameters/{parameter}', [SystemSettingsController::class, 'destroyParameter'])
            ->middleware('permission:manage settings')
            ->name('parameters.destroy');

        Route::post('units', [SystemSettingsController::class, 'storeUnit'])
            ->middleware('permission:manage settings')
            ->name('units.store');

        Route::patch('units/{unit}', [SystemSettingsController::class, 'updateUnit'])
            ->middleware('permission:manage settings')
            ->name('units.update');

        Route::delete('units/{unit}', [SystemSettingsController::class, 'destroyUnit'])
            ->middleware('permission:manage settings')
            ->name('units.destroy');

        Route::patch('categories/{category}', [SystemSettingsController::class, 'updateCategory'])
            ->middleware('permission:manage settings')
            ->name('categories.update');
    });

    Route::get('activity-logs', [ActivityLogController::class, 'index'])
        ->middleware('permission:view activity logs')
        ->name('activity-logs.index');

    /*
    |--------------------------------------------------------------------------
    | Basin registry
    |--------------------------------------------------------------------------
    | Route middleware is the first gate; controllers repeat every decision
    | through BasinPolicy / SubBasinPolicy so a missing middleware line cannot
    | expose a write operation.
    */

    Route::prefix('basins')->name('basins.')->group(function () {
        Route::get('/', [BasinController::class, 'index'])
            ->middleware('permission:view basins')
            ->name('index');

        Route::get('export', [BasinController::class, 'export'])
            ->middleware('permission:view basins')
            ->name('export');

        Route::get('create', [BasinController::class, 'create'])
            ->middleware('permission:create basins')
            ->name('create');

        Route::post('/', [BasinController::class, 'store'])
            ->middleware('permission:create basins')
            ->name('store');

        Route::get('{basin}', [BasinController::class, 'show'])
            ->middleware('permission:view basins')
            ->name('show');

        Route::get('{basin}/edit', [BasinController::class, 'edit'])
            ->middleware('permission:edit basins')
            ->name('edit');

        Route::patch('{basin}', [BasinController::class, 'update'])
            ->middleware('permission:edit basins')
            ->name('update');

        Route::delete('{basin}', [BasinController::class, 'destroy'])
            ->middleware('permission:delete basins')
            ->name('destroy');

        Route::post('{basin}/boundary', [BasinController::class, 'storeBoundary'])
            ->middleware('permission:import basin boundaries')
            ->name('boundary.store');

        Route::delete('{basin}/boundary/{boundary}', [BasinController::class, 'destroyBoundary'])
            ->middleware('permission:import basin boundaries')
            ->name('boundary.destroy');

        Route::post('{basin}/documents', [BasinController::class, 'storeDocument'])
            ->middleware('permission:edit basins')
            ->name('documents.store');

        Route::get('{basin}/documents/{document}', [BasinController::class, 'downloadDocument'])
            ->middleware('permission:view basins')
            ->name('documents.download');

        Route::delete('{basin}/documents/{document}', [BasinController::class, 'destroyDocument'])
            ->middleware('permission:edit basins')
            ->name('documents.destroy');
    });

    Route::prefix('sub-basins')->name('sub-basins.')->group(function () {
        Route::get('/', [SubBasinController::class, 'index'])
            ->middleware('permission:view sub-basins')
            ->name('index');

        Route::get('export', [SubBasinController::class, 'export'])
            ->middleware('permission:view sub-basins')
            ->name('export');

        Route::get('create', [SubBasinController::class, 'create'])
            ->middleware('permission:create sub-basins')
            ->name('create');

        Route::post('/', [SubBasinController::class, 'store'])
            ->middleware('permission:create sub-basins')
            ->name('store');

        Route::get('{subBasin}', [SubBasinController::class, 'show'])
            ->middleware('permission:view sub-basins')
            ->name('show');

        Route::get('{subBasin}/edit', [SubBasinController::class, 'edit'])
            ->middleware('permission:edit sub-basins')
            ->name('edit');

        Route::patch('{subBasin}', [SubBasinController::class, 'update'])
            ->middleware('permission:edit sub-basins')
            ->name('update');

        Route::delete('{subBasin}', [SubBasinController::class, 'destroy'])
            ->middleware('permission:delete sub-basins')
            ->name('destroy');

        Route::post('{subBasin}/boundary', [SubBasinController::class, 'storeBoundary'])
            ->middleware('permission:import basin boundaries')
            ->name('boundary.store');

        Route::delete('{subBasin}/boundary', [SubBasinController::class, 'destroyBoundary'])
            ->middleware('permission:import basin boundaries')
            ->name('boundary.destroy');

        Route::post('{subBasin}/documents', [SubBasinController::class, 'storeDocument'])
            ->middleware('permission:edit sub-basins')
            ->name('documents.store');

        Route::get('{subBasin}/documents/{document}', [SubBasinController::class, 'downloadDocument'])
            ->middleware('permission:view sub-basins')
            ->name('documents.download');

        Route::delete('{subBasin}/documents/{document}', [SubBasinController::class, 'destroyDocument'])
            ->middleware('permission:edit sub-basins')
            ->name('documents.destroy');
    });
});

/*
|--------------------------------------------------------------------------
| Interactive GIS
|--------------------------------------------------------------------------
| The map page is Inertia; geometry is served as GeoJSON from JSON routes
| guarded by the same permission middleware.
*/

Route::prefix('gis')->middleware('auth')->name('gis.')->group(function () {
    Route::get('/', [GisController::class, 'index'])
        ->middleware('permission:view map')
        ->name('index');

    Route::get('data/basins', [GisController::class, 'basins'])
        ->middleware('permission:view map')
        ->name('data.basins');

    Route::get('data/basins/{basin}', [GisController::class, 'basin'])
        ->middleware('permission:view map')
        ->name('data.basin');

    Route::get('data/sub-basins/{subBasin}', [GisController::class, 'subBasin'])
        ->middleware('permission:view map')
        ->name('data.sub-basin');

    Route::get('data/stations', [GisController::class, 'stations'])
        ->middleware('permission:view map')
        ->name('data.stations');

    Route::get('data/stations/rainfall', [GisController::class, 'stationsRainfall'])
        ->middleware('permission:view map')
        ->name('data.stations.rainfall');

    Route::get('data/stations/{station}/series', [GisController::class, 'stationSeries'])
        ->middleware('permission:view map')
        ->name('data.stations.series');

    Route::get('data/stations/{station}', [GisController::class, 'station'])
        ->middleware('permission:view map')
        ->name('data.station');

    Route::get('data/features/{layer}', [GisController::class, 'features'])
        ->middleware('permission:view map')
        ->whereIn('layer', ['national_boundary', 'rivers', 'lakes'])
        ->name('data.features');

    Route::get('data/watersheds', [GisController::class, 'watersheds'])
        ->middleware('permission:view map')
        ->name('data.watersheds');

    Route::get('data/search', [GisController::class, 'search'])
        ->middleware('permission:view map')
        ->name('data.search');

    Route::post('features', [GisController::class, 'storeFeature'])
        ->middleware('permission:manage geographic boundaries')
        ->name('features.store');

    Route::delete('features/{feature}', [GisController::class, 'destroyFeature'])
        ->middleware('permission:manage geographic boundaries')
        ->name('features.destroy');
});

/*
|--------------------------------------------------------------------------
| Excel data transformation
|--------------------------------------------------------------------------
| Upload → detect → map → transform → validate → compare → import. Every
| route is permission-guarded; the original workbook is never modified.
*/

Route::prefix('excel')->middleware('auth')->name('excel.')->group(function () {
    Route::get('/', [ExcelImportController::class, 'index'])
        ->middleware('permission:view data')
        ->name('index');

    Route::get('upload', [ExcelImportController::class, 'create'])
        ->middleware('permission:upload data')
        ->name('upload');

    Route::post('upload', [ExcelImportController::class, 'store'])
        ->middleware('permission:upload data')
        ->name('store');

    Route::get('history', [ExcelImportController::class, 'history'])
        ->middleware('permission:view data')
        ->name('history');

    Route::get('{batch:uuid}/preview', [ExcelPreviewController::class, 'show'])
        ->middleware('permission:view data')
        ->name('preview');

    Route::get('{batch:uuid}/sheets/{sheet}', [ExcelPreviewController::class, 'sheet'])
        ->middleware('permission:view data')
        ->name('sheet');

    Route::get('{batch:uuid}/mapping', [ExcelTransformationController::class, 'mapping'])
        ->middleware('permission:transform data')
        ->name('mapping');

    Route::post('{batch:uuid}/mapping', [ExcelTransformationController::class, 'storeMapping'])
        ->middleware('permission:transform data')
        ->name('mapping.store');

    Route::get('{batch:uuid}/transformation', [ExcelTransformationController::class, 'show'])
        ->middleware('permission:view data')
        ->name('transformation');

    Route::post('{batch:uuid}/transform', [ExcelTransformationController::class, 'transform'])
        ->middleware('permission:transform data')
        ->name('transform');

    Route::post('{batch:uuid}/compare', [ExcelTransformationController::class, 'compare'])
        ->middleware('permission:transform data')
        ->name('compare');

    Route::get('{batch:uuid}/comparison', [ExcelPreviewController::class, 'comparison'])
        ->middleware('permission:view data')
        ->name('comparison');

    Route::get('{batch:uuid}/validation', [ExcelValidationController::class, 'show'])
        ->middleware('permission:view data')
        ->name('validation');

    Route::post('{batch:uuid}/validate', [ExcelValidationController::class, 'validate'])
        ->middleware('permission:transform data')
        ->name('validate');

    Route::get('{batch:uuid}/export/transformed', [ExcelExportController::class, 'transformed'])
        ->middleware('permission:export data')
        ->name('export.transformed');

    Route::get('{batch:uuid}/export/errors', [ExcelExportController::class, 'errors'])
        ->middleware('permission:export data')
        ->name('export.errors');

    Route::get('{batch:uuid}/export/geojson', [ExcelExportController::class, 'geojson'])
        ->middleware('permission:export data')
        ->name('export.geojson');

    Route::post('{batch:uuid}/import', [ExcelImportController::class, 'import'])
        ->middleware('permission:approve data')
        ->name('import');
});
Route::middleware('auth')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Reports and analytics
    |--------------------------------------------------------------------------
    |
    | Viewing requires `view reports`. Every download additionally requires
    | `export reports`, and a report that would carry provisional observations
    | requires `export restricted reports`, enforced in the controllers.
    */

    Route::prefix('reports')->name('reports.')->group(function () {
        Route::get('/', ReportController::class)
            ->middleware('permission:view reports')
            ->name('index');

        Route::get('analysis', [AnalysisReportController::class, 'index'])
            ->middleware('permission:view reports')
            ->name('analysis');

        Route::get('analysis/data', [AnalysisReportController::class, 'data'])
            ->middleware('permission:view reports')
            ->name('analysis.data');

        Route::get('basins/{basin}', [BasinReportController::class, 'show'])
            ->middleware('permission:view reports')
            ->name('basins.show');

        Route::get('stations/{station}', [StationReportController::class, 'show'])
            ->middleware('permission:view reports')
            ->name('stations.show');

        Route::get('basins/{basin}/export/{format}', [BasinReportController::class, 'export'])
            ->whereIn('format', ['csv', 'xlsx', 'pdf', 'geojson'])
            ->middleware('permission:export reports')
            ->name('basins.export');

        Route::get('stations/{station}/export/{format}', [StationReportController::class, 'export'])
            ->whereIn('format', ['csv', 'xlsx', 'pdf', 'geojson'])
            ->middleware('permission:export reports')
            ->name('stations.export');

        Route::get('analysis/export/{format}', [AnalysisReportController::class, 'export'])
            ->whereIn('format', ['csv', 'xlsx', 'pdf'])
            ->middleware('permission:export reports')
            ->name('analysis.export');
    });
});

require __DIR__.'/auth.php';
