<?php

namespace App\Providers;

use App\Domain\Activities\Contracts\ActivitySourceEnricher;
use App\Domain\Activities\Contracts\ActivitySourceFileParser;
use App\Domain\Activities\Services\FitActivitySourceEnricher;
use App\Domain\Activities\Services\ProviderRegistry;
use App\Infrastructure\Fit\NodeFitFileParser;
use App\Infrastructure\Providers\Hammerhead\HammerheadProvider;
use App\Modules\Heatmap\Contracts\RouteGeometryProvider;
use App\Modules\Heatmap\Services\StreamRouteGeometryProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ActivitySourceFileParser::class, NodeFitFileParser::class);
        $this->app->bind(ActivitySourceEnricher::class, FitActivitySourceEnricher::class);
        $this->app->bind(RouteGeometryProvider::class, StreamRouteGeometryProvider::class);

        $this->app->singleton(ProviderRegistry::class, fn ($app) => new ProviderRegistry([
            $app->make(HammerheadProvider::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! app()->isProduction());
    }
}
