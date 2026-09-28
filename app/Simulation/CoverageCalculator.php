<?php

namespace App\Simulation;

final class CoverageCalculator
{
    public function __construct(private float $radiusM, private int $gridSize) {}

    /**
     * @param  list<array{0: float, 1: float}>  $ring  GeoJSON ring [lng, lat]
     * @param  list<array{0: float, 1: float}>  $stops  [lat, lng]
     */
    public function percent(array $ring, array $stops): float
    {
        $points = Geo::grid($ring, $this->gridSize);
        if ($points === [] || $stops === []) {
            return 0.0;
        }

        $covered = 0;
        foreach ($points as [$lat, $lng]) {
            foreach ($stops as [$sLat, $sLng]) {
                if (Geo::distanceM($lat, $lng, $sLat, $sLng) <= $this->radiusM) {
                    $covered++;
                    break;
                }
            }
        }

        return 100.0 * $covered / count($points);
    }

    /**
     * Доля района в коридоре вдоль линий (расширенная дорога), %.
     *
     * @param  list<list<array{0: float, 1: float}>>  $lines  MultiLineString [lng, lat]
     */
    public function percentNearLines(array $ring, array $lines): float
    {
        $points = Geo::grid($ring, $this->gridSize);
        if ($points === [] || $lines === []) {
            return 0.0;
        }
        $covered = count(array_filter($points, fn (array $p) => Geo::distanceToLinesM($p[0], $p[1], $lines) <= $this->radiusM));

        return 100.0 * $covered / count($points);
    }
}
