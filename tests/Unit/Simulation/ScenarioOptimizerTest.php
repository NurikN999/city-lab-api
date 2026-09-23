<?php

namespace Tests\Unit\Simulation;

use App\Simulation\Data\Coupling;
use App\Simulation\Data\EffectDef;
use App\Simulation\Data\RouteDef;
use App\Simulation\Goal;
use App\Simulation\ScenarioOptimizer;
use PHPUnit\Framework\TestCase;

class ScenarioOptimizerTest extends TestCase
{
    use CityFixture;

    private function actions(): array
    {
        return [
            $this->action('lights', 10_000_000, [new EffectDef('traffic', -6)]),
            $this->action('lane', 30_000_000, [new EffectDef('traffic', -4)]),
            $this->action('green', 18_000_000, [new EffectDef('heat', -6)]),
            $this->action('dust', 25_000_000, [new EffectDef('air', 9)]),
        ];
    }

    public function test_only_relevant_actions_are_combined(): void
    {
        $result = (new ScenarioOptimizer($this->service()))->optimize(
            $this->city(), 1, $this->actions(), null, [], [new Goal('traffic', 'decrease')], 40_000_000,
        );

        $this->assertSame(3, $result->combinations); // {lights}, {lane}, {lights, lane}
        $this->assertSame(3, $result->withinBudget);
        $this->assertSame(['lights', 'lane'], $result->scenarios[0]->keys());
        foreach ($result->scenarios as $s) {
            $this->assertNotContains('green', $s->keys());
            $this->assertNotContains('dust', $s->keys());
        }
    }

    public function test_respects_budget_and_limit_and_diversity(): void
    {
        $result = (new ScenarioOptimizer($this->service()))->optimize(
            $this->city(), 1, $this->actions(), null, [],
            [new Goal('traffic', 'decrease'), new Goal('heat', 'decrease', 0.5)], 50_000_000,
        );

        $this->assertLessThanOrEqual(3, count($result->scenarios));
        foreach ($result->scenarios as $i => $s) {
            $this->assertLessThanOrEqual(50_000_000, $s->result->cost);
            foreach (array_slice($result->scenarios, 0, $i) as $prev) {
                $union = count(array_unique([...$s->keys(), ...$prev->keys()]));
                $this->assertLessThan(0.67, count(array_intersect($s->keys(), $prev->keys())) / $union);
            }
        }
    }

    public function test_small_budget_limits_options(): void
    {
        $result = (new ScenarioOptimizer($this->service()))->optimize(
            $this->city(), 1, $this->actions(), null, [], [new Goal('traffic', 'decrease')], 20_000_000,
        );

        $this->assertSame(1, $result->withinBudget);
        $this->assertSame(['lights'], $result->scenarios[0]->keys());
    }

    public function test_route_is_candidate_through_coverage_coupling(): void
    {
        $city = $this->city([new Coupling('transit_coverage', 'traffic', -0.35)], [], withCoverage: true);
        $routeAction = $this->action('new_bus_route', 42_000_000, [], 'route');
        $route = new RouteDef(1, 'b', 'Маршрут Б', [[43.650, 51.150]], [1]);

        $result = (new ScenarioOptimizer($this->service()))->optimize(
            $city, 1, $this->actions(), $routeAction, [$route], [new Goal('traffic', 'decrease')], 100_000_000,
        );

        $this->assertSame(7, $result->combinations); // 2 route options × 4 masks − empty
        $this->assertContains('new_bus_route:b', $result->scenarios[0]->keys());
    }

    public function test_route_not_serving_district_is_ignored(): void
    {
        $city = $this->city([new Coupling('transit_coverage', 'traffic', -0.35)], [], withCoverage: true);
        $routeAction = $this->action('new_bus_route', 42_000_000, [], 'route');
        $elsewhere = new RouteDef(2, 'c', 'Маршрут В', [[43.700, 51.250]], [3]);

        $result = (new ScenarioOptimizer($this->service()))->optimize(
            $city, 1, $this->actions(), $routeAction, [$elsewhere], [new Goal('traffic', 'decrease')], 100_000_000,
        );

        $this->assertSame(3, $result->combinations);
    }
}
