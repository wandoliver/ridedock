<?php

namespace App\Providers;

use App\Domain\Accounts\Enums\UserRole;
use App\Domain\Accounts\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use App\Domain\Activities\Contracts\ActivitySourceEnricher;
use App\Domain\Activities\Contracts\ActivitySourceFileParser;
use App\Domain\Activities\Services\FitActivitySourceEnricher;
use App\Domain\Activities\Services\ProviderRegistry;
use App\Domain\Training\Contracts\GoalTrainingPlanner;
use App\Domain\Training\Contracts\TrainingAdvisor;
use App\Domain\Training\Contracts\TrainingBlockPlanner;
use App\Domain\Training\Contracts\WeeklyTrainingPlanner;
use App\Infrastructure\Ai\OpenAiTrainingAdvisor;
use App\Infrastructure\Fit\NodeFitFileParser;
use App\Infrastructure\Providers\Hammerhead\HammerheadProvider;
use App\Infrastructure\Providers\Wahoo\WahooProvider;
use App\Modules\Heatmap\Contracts\RouteGeometryProvider;
use App\Modules\Heatmap\Services\StreamRouteGeometryProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Domain\Operations\Services\AiUsage::class);
        $this->app->bind(ActivitySourceFileParser::class, NodeFitFileParser::class);
        $this->app->bind(ActivitySourceEnricher::class, FitActivitySourceEnricher::class);
        $this->app->bind(RouteGeometryProvider::class, StreamRouteGeometryProvider::class);
        $this->app->singleton(OpenAiTrainingAdvisor::class, fn ($app): OpenAiTrainingAdvisor => new OpenAiTrainingAdvisor(
            http: $app->make(Factory::class),
            apiKey: (string) config('ai.providers.openai.api_key'),
            model: (string) config('ai.providers.openai.model'),
            baseUrl: (string) config('ai.providers.openai.base_url'),
            timeoutSeconds: (int) config('ai.providers.openai.timeout_seconds'),
        ));
        $this->app->bind(TrainingAdvisor::class, fn ($app): TrainingAdvisor => $app->make(OpenAiTrainingAdvisor::class));
        $this->app->bind(GoalTrainingPlanner::class, fn ($app): GoalTrainingPlanner => $app->make(OpenAiTrainingAdvisor::class));
        $this->app->bind(TrainingBlockPlanner::class, fn ($app): TrainingBlockPlanner => $app->make(OpenAiTrainingAdvisor::class));
        $this->app->bind(WeeklyTrainingPlanner::class, fn ($app): WeeklyTrainingPlanner => $app->make(OpenAiTrainingAdvisor::class));

        $this->app->singleton(ProviderRegistry::class, fn ($app) => new ProviderRegistry([
            $app->make(HammerheadProvider::class),
            $app->make(WahooProvider::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Queue::createPayloadUsing(
            fn ($connection, $queue, $payload): array =>
            app(\App\Domain\Privacy\Services\AccountQueueData::class)->payloadFor($payload['data']['command'] ?? null)
        );
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Queue\Events\JobQueued::class, [
            \App\Domain\Privacy\Services\AccountQueueData::class, 'cleanLateJob',
        ]);
        if (config('queue.failed.driver') === 'database-uuids') {
            $this->app->singleton('queue.failer', fn ($app) => new \App\Infrastructure\Queue\PrivateFailedJobProvider(
                $app['db'],
                config('queue.failed.database'),
                config('queue.failed.table')
            ));
        }
        Gate::define('manage-users', fn (User $user): bool => $user->role === UserRole::Admin && $user->status === UserStatus::Active);
        Model::preventLazyLoading(! app()->isProduction());

        if (app()->environment('local')
            && file_exists(base_path('.certs/localhost.pem'))
            && file_exists(base_path('.certs/localhost-key.pem'))) {
            DevCommands::artisan('serve --host=127.0.0.1 --port=8002 --tries=1', 'server');
            DevCommands::register('node scripts/https-proxy.mjs', 'https')->green();
        }

        if (config('ai.queue_connection') !== 'sync') {
            DevCommands::artisan('queue:work '.config('ai.queue_connection').' --queue=ai --sleep=1 --timeout=120', 'ai-worker');
        }

        if (config('queue.default') === 'sync') {
            DevCommands::except('queue');
        }
    }
}
