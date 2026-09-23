<?php

namespace App\Simulation;

final class Geo
{
    private const EARTH_RADIUS_M = 6_371_000;

    public static function distanceM(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * self::EARTH_RADIUS_M * asin(min(1.0, sqrt($a)));
    }

    /** @param list<array{0: float, 1: float}> $ring GeoJSON ring of [lng, lat] */
    public static function contains(array $ring, float $lat, float $lng): bool
    {
        $inside = false;
        $n = count($ring);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $ring[$i];
            [$xj, $yj] = $ring[$j];
            if (($yi > $lat) !== ($yj > $lat) && $lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /** @return list<array{0: float, 1: float}> [lat, lng] cell centers of an n×n bbox grid that fall inside the ring */
    public static function grid(array $ring, int $n): array
    {
        $lngs = array_column($ring, 0);
        $lats = array_column($ring, 1);
        [$minLng, $maxLng, $minLat, $maxLat] = [min($lngs), max($lngs), min($lats), max($lats)];

        $points = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $lat = $minLat + ($i + 0.5) * ($maxLat - $minLat) / $n;
                $lng = $minLng + ($j + 0.5) * ($maxLng - $minLng) / $n;
                if (self::contains($ring, $lat, $lng)) {
                    $points[] = [$lat, $lng];
                }
            }
        }

        return $points;
    }
}
