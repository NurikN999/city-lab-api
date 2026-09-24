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

    /** Площадь кольца GeoJSON ([lng, lat]) в гектарах; равнопромежуточная проекция — точна для районов города. */
    public static function areaHa(array $ring): float
    {
        $lat0 = deg2rad(array_sum(array_column($ring, 1)) / count($ring));
        $x = fn (array $p) => deg2rad($p[0]) * self::EARTH_RADIUS_M * cos($lat0);
        $y = fn (array $p) => deg2rad($p[1]) * self::EARTH_RADIUS_M;
        $sum = 0.0;
        for ($i = 0, $n = count($ring); $i < $n; $i++) {
            $a = $ring[$i];
            $b = $ring[($i + 1) % $n];
            $sum += $x($a) * $y($b) - $x($b) * $y($a);
        }

        return abs($sum) / 2 / 10_000;
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
