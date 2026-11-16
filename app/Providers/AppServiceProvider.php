<?php

namespace App\Providers;

use App\Models\Basin;
use App\Models\DataImportBatch;
use App\Models\Document;
use App\Models\MeasurementUnit;
use App\Models\MonitoringStation;
use App\Models\Parameter;
use App\Models\SubBasin;
use App\Models\User;
use App\Observers\AuditableObserver;
use App\Services\Audit\ActivityLogger;
use App\Services\Settings\SystemSettingService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The local install predates a completed Composer run; keep the XLSX
        // writer autoloadable without editing generated vendor files.
        $loader = require base_path('vendor/autoload.php');
        $loader->addPsr4('ZipStream\\', base_path('vendor/maennchen/zipstream-php/src'));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /**
         * Super Admin holds unrestricted application permissions. Returning
         * null (rather than false) keeps the normal policy chain intact for
         * every other role.
         */
        Gate::before(function (?User $user): ?bool {
            return $user?->hasRole(config('access.super_admin_role')) ? true : null;
        });

        /**
         * Stored settings override their config defaults before anything reads
         * them, so the rest of the application keeps using config().
         */
        $this->app->make(SystemSettingService::class)->apply();

        /**
         * Authentication events belong in the audit trail the administrator
         * reviews. Neither listener records a credential.
         */
        Event::listen(Login::class, function (Login $event): void {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();

            $this->app->make(ActivityLogger::class)->log(
                'auth.login',
                $event->user,
                "{$event->user->email} signed in",
                ['guard' => $event->guard],
                $event->user,
            );
        });

        Event::listen(Logout::class, function (Logout $event): void {
            if (! $event->user) {
                return;
            }

            $this->app->make(ActivityLogger::class)->log(
                'auth.logout',
                $event->user,
                "{$event->user->email} signed out",
                ['guard' => $event->guard],
                $event->user,
            );
        });

        /**
         * Records the brief lists as auditable: creation, updates and deletion
         * are stored with the actor and the changed attributes.
         */
        foreach ([
            Basin::class,
            SubBasin::class,
            MonitoringStation::class,
            Parameter::class,
            MeasurementUnit::class,
            Document::class,
            DataImportBatch::class,
        ] as $model) {
            $model::observe(AuditableObserver::class);
        }
    }
}
