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
        if (count($ring) < 3) {
            return 0.0;
        }
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

    /**
     * Расстояние от точки до ближайшего отрезка ломаных, м (локальная равнопромежуточная проекция — точна в пределах города).
     *
     * @param  list<list<array{0: float, 1: float}>>  $lines  MultiLineString [lng, lat]
     */
    public static function distanceToLinesM(float $lat, float $lng, array $lines): float
    {
        $kx = deg2rad(1) * self::EARTH_RADIUS_M * cos(deg2rad($lat));
        $ky = deg2rad(1) * self::EARTH_RADIUS_M;
        $best = INF;
        foreach ($lines as $line) {
            for ($i = 1, $n = count($line); $i < $n; $i++) {
                [$ax, $ay] = [($line[$i - 1][0] - $lng) * $kx, ($line[$i - 1][1] - $lat) * $ky];
                [$bx, $by] = [($line[$i][0] - $lng) * $kx, ($line[$i][1] - $lat) * $ky];
                $len2 = ($bx - $ax) ** 2 + ($by - $ay) ** 2;
                $t = $len2 > 0 ? max(0.0, min(1.0, -($ax * ($bx - $ax) + $ay * ($by - $ay)) / $len2)) : 0.0;
                $best = min($best, hypot($ax + $t * ($bx - $ax), $ay + $t * ($by - $ay)));
            }
        }

        return $best;
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
