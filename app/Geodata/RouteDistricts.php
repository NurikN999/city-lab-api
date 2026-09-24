<?php

namespace App\Geodata;

use App\Models\District;
use App\Simulation\Geo;

final class RouteDistricts
{
    /**
     * Район обслуживается, если хотя бы одна остановка внутри полигона или не дальше радиуса от центра.
     *
     * @param  iterable<District>  $districts
     * @param  list<array{0: float, 1: float}>  $stops  [lat, lng]
     * @return list<int>
     */
    public static function serving(iterable $districts, array $stops, float $radiusM): array
    {
        $ids = [];
        foreach ($districts as $district) {
            foreach ($stops as [$lat, $lng]) {
                if (Geo::contains($district->boundary['coordinates'][0], $lat, $lng)
                    || Geo::distanceM($lat, $lng, $district->center_lat, $district->center_lng) <= $radiusM) {
                    $ids[] = $district->id;
                    break;
                }
            }
        }

        return $ids;
    }
}
