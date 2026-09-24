<?php

namespace App\Console\Commands;

use App\Models\BusRoute;
use App\Models\District;
use App\Models\Stop;
use App\Simulation\Geo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportGeodata extends Command
{
    protected $signature = 'city:import-geodata {dir : Папка с districts.geojson, stops.json (Overpass) и routes.geojson}';

    protected $description = 'Импорт реальных полигонов районов, остановок и маршрутов Актау';

    public function handle(): int
    {
        $dir = rtrim($this->argument('dir'), '/');
        $files = ['districts.geojson', 'stops.json', 'routes.geojson'];
        foreach ($files as $file) {
            if (! is_file("{$dir}/{$file}") || ! json_validate(file_get_contents("{$dir}/{$file}"))) {
                $this->error("Нет файла или невалидный JSON: {$dir}/{$file}");

                return self::FAILURE;
            }
        }
        [$districts, $stops, $routes] = array_map(
            fn ($f) => json_decode(file_get_contents("{$dir}/{$f}"), true, flags: JSON_THROW_ON_ERROR),
            $files,
        );

        $imported = 0;
        DB::transaction(function () use ($districts, $stops, $routes, &$imported) {
            foreach ($districts['features'] as $feature) {
                $name = $feature['properties']['name'];
                $ring = $feature['geometry']['coordinates'][0];
                $vertices = array_slice($ring, 0, -1);
                $district = District::firstOrNew(['name' => $name]);
                $district->fill([
                    'boundary' => $feature['geometry'],
                    'center_lng' => array_sum(array_column($vertices, 0)) / count($vertices),
                    'center_lat' => array_sum(array_column($vertices, 1)) / count($vertices),
                    'population' => $feature['properties']['population'] ?? $district->population ?? 0,
                ]);
                if (! $district->exists) {
                    $this->warn("Новый район «{$name}»: задайте метрики через PUT /api/districts/{id}.");
                }
                $district->save();
            }

            Stop::query()->delete();
            foreach ($stops['elements'] as $element) {
                if (($element['type'] ?? null) === 'node') {
                    Stop::create(['name' => $element['tags']['name'] ?? null, 'lat' => $element['lat'], 'lng' => $element['lon']]);
                }
            }

            $allDistricts = District::all();
            $radius = config('simulation.stop_access_radius_m');
            foreach ($routes['features'] as $i => $feature) {
                if (($feature['geometry']['type'] ?? null) !== 'LineString' || ! isset($feature['properties']['key'], $feature['properties']['name'])) {
                    $this->warn("Маршрут #{$i} пропущен: нужна линия с properties.key и properties.name.");

                    continue;
                }
                $route = BusRoute::updateOrCreate(
                    ['key' => $feature['properties']['key']],
                    ['name' => $feature['properties']['name'], 'path' => $feature['geometry']],
                );
                $route->stops()->delete();
                $served = [];
                foreach ($feature['geometry']['coordinates'] as $position => [$lng, $lat]) {
                    $route->stops()->create(['position' => $position, 'lat' => $lat, 'lng' => $lng]);
                    foreach ($allDistricts as $d) {
                        if (Geo::contains($d->boundary['coordinates'][0], $lat, $lng)
                            || Geo::distanceM($lat, $lng, $d->center_lat, $d->center_lng) <= $radius) {
                            $served[$d->id] = true;
                        }
                    }
                }
                $route->districts()->sync(array_keys($served));
                $imported++;
            }
        });

        $this->info(sprintf('Импортировано: районов %d, остановок %d, маршрутов %d.',
            count($districts['features']), Stop::count(), $imported));

        return self::SUCCESS;
    }
}
