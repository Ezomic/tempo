<?php

namespace App\Providers;

use App\Services\Chronos\ChronosClient;
use App\Services\Garmin\GarminClient;
use App\Services\Garmin\SidecarGarminClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(GarminClient::class, fn (): SidecarGarminClient => new SidecarGarminClient(
            baseUrl: (string) config('services.garmin_sidecar.url'),
            secret: (string) config('services.garmin_sidecar.secret'),
        ));

        $this->app->bind(ChronosClient::class, fn (): ChronosClient => new ChronosClient(
            baseUrl: config('services.chronos.url'),
            token: config('services.chronos.token'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
