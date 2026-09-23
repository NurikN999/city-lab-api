<?php

namespace Tests\Unit\Simulation;

use App\Simulation\Data\ActionDef;
use App\Simulation\Data\CityState;
use App\Simulation\Data\Coupling;
use App\Simulation\Data\DistrictState;
use App\Simulation\Data\EffectDef;
use App\Simulation\Data\MetricDef;
use App\Simulation\SimulationService;

trait CityFixture
{
    private int $nextActionId = 0;

    /** GeoJSON ring of a square around a point; $half in degrees (0.003 ≈ 330 м по широте). */
    protected function square(float $lat, float $lng, float $half = 0.003): array
    {
        return [
            [$lng - $half, $lat - $half], [$lng + $half, $lat - $half],
            [$lng + $half, $lat + $half], [$lng - $half, $lat + $half], [$lng - $half, $lat - $half],
        ];
    }

    protected function district(int $id, float $lat, float $lng, array $values, int $population = 1000): DistrictState
    {
        return new DistrictState($id, "{$id} мкр", $population, $lat, $lng, $this->square($lat, $lng), $values);
    }

    /** @return array<string, MetricDef> */
    protected function metrics(bool $withCoverage = false): array
    {
        $metrics = [
            'traffic' => new MetricDef('traffic', true, 0, 100, 0.3),
            'heat' => new MetricDef('heat', true, 0, 100, 0.2),
            'air' => new MetricDef('air', false, 0, 100),
            'satisfaction' => new MetricDef('satisfaction', false, 0, 100),
        ];
        if ($withCoverage) {
            $metrics['transit_coverage'] = new MetricDef('transit_coverage', false, 0, 100, 0.0, true);
        }

        return $metrics;
    }

    /**
     * District 1 (43.650, 51.150), district 2 ≈ 805 м east (neighbor), district 3 ≈ 9 км away.
     *
     * @param  list<Coupling>  $couplings
     * @param  list<array{0: float, 1: float}>  $stops
     */
    protected function city(array $couplings = [], array $stops = [], bool $withCoverage = false): CityState
    {
        $values = ['traffic' => 80.0, 'heat' => 70.0, 'air' => 40.0, 'satisfaction' => 50.0];

        return new CityState(
            [
                1 => $this->district(1, 43.650, 51.150, $values, 1000),
                2 => $this->district(2, 43.650, 51.160, ['traffic' => 60.0] + $values, 3000),
                3 => $this->district(3, 43.700, 51.250, $values, 1000),
            ],
            $this->metrics($withCoverage),
            $couplings,
            $stops,
        );
    }

    /** @param list<EffectDef> $effects */
    protected function action(string $key, int $cost, array $effects = [], string $scope = 'district'): ActionDef
    {
        return new ActionDef(++$this->nextActionId, $key, $key, $cost, $scope, $effects, "допущение {$key}");
    }

    protected function service(): SimulationService
    {
        return new SimulationService([
            'diminishing_factor' => 0.6, 'neighbor_radius_m' => 1500,
            'stop_access_radius_m' => 500, 'coverage_grid' => 12,
        ]);
    }
}
