<?php

namespace App\Providers;

use App\CityAi\OpenAiClient;
use App\Simulation\SimulationService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SimulationService::class, fn () => new SimulationService(config('simulation')));
        $this->app->bind(OpenAiClient::class, fn () => new OpenAiClient(
            config('services.openai.key'),
            config('services.openai.model'),
            (int) config('services.openai.timeout', 8),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();
    }
}
