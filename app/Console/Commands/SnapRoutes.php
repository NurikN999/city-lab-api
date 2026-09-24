<?php

namespace App\Console\Commands;

use App\Geodata\OsrmClient;
use App\Geodata\RouteWriter;
use App\Models\BusRoute;
use Illuminate\Console\Command;

class SnapRoutes extends Command
{
    protected $signature = 'city:snap-routes {keys* : Ключи маршрутов, например b c}';

    protected $description = 'Перестроить линии маршрутов по дорогам (OSRM) через их текущие остановки';

    public function handle(OsrmClient $osrm): int
    {
        foreach ($this->argument('keys') as $key) {
            $route = BusRoute::with('stops')->where('key', $key)->firstOrFail();
            $routed = $osrm->route($route->stops->map(fn ($s) => [$s->lat, $s->lng])->all());
            if (! $routed['snapped']) {
                $this->error("{$route->name}: OSRM недоступен, маршрут не изменён.");

                return self::FAILURE;
            }
            RouteWriter::save($key, $route->name, $routed);
            $this->info("{$route->name} перестроен по дорогам (точек линии: ".count($routed['path']).').');
        }

        return self::SUCCESS;
    }
}
