<?php

namespace App\Geodata;

use App\Models\BusRoute;
use App\Models\District;
use Illuminate\Support\Facades\DB;

final class RouteWriter
{
    /** @param array{path: list<array{0: float, 1: float}>, stops: list<array{0: float, 1: float}>, snapped: bool} $routed */
    public static function save(string $key, string $name, array $routed): BusRoute
    {
        return DB::transaction(function () use ($key, $name, $routed) {
            $route = BusRoute::updateOrCreate(
                ['key' => $key],
                ['name' => $name, 'path' => ['type' => 'LineString', 'coordinates' => $routed['path']]],
            );
            $route->stops()->delete();
            foreach ($routed['stops'] as $position => [$lat, $lng]) {
                $route->stops()->create(['position' => $position, 'lat' => $lat, 'lng' => $lng]);
            }
            $route->districts()->sync(RouteDistricts::serving(
                District::all(), $routed['stops'], config('simulation.stop_access_radius_m'),
            ));

            return $route->load(['stops', 'districts:id']);
        });
    }
}
