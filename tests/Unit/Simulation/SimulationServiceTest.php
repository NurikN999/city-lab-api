<?php

namespace Tests\Unit\Simulation;

use App\Simulation\BudgetExceededException;
use App\Simulation\Data\CityState;
use App\Simulation\Data\Coupling;
use App\Simulation\Data\EffectDef;
use App\Simulation\Data\PlannedItem;
use App\Simulation\Data\RouteDef;
use PHPUnit\Framework\TestCase;

class SimulationServiceTest extends TestCase
{
    use CityFixture;

    public function test_empty_scenario_changes_nothing(): void
    {
        $result = $this->service()->simulate($this->city(), [], 100_000_000);

        $this->assertSame($result->before, $result->after);
        $this->assertSame(0, $result->cost);
    }

    public function test_effect_applies_percent_to_target_district(): void
    {
        $lights = $this->action('lights', 10_000_000, [new EffectDef('traffic', -10)]);

        $result = $this->service()->simulate($this->city(), [new PlannedItem($lights, districtId: 1)], 100_000_000);

        $this->assertEqualsWithDelta(72.0, $result->after['districts'][1]['traffic'], 0.001);
        $this->assertEqualsWithDelta(60.0, $result->after['districts'][2]['traffic'], 0.001);
    }

    public function test_repeat_has_diminishing_return(): void
    {
        $lights = $this->action('lights', 10_000_000, [new EffectDef('traffic', -10)]);

        $result = $this->service()->simulate($this->city(), [new PlannedItem($lights, districtId: 1, quantity: 2)], 100_000_000);

        // 80 × 0.9 × (1 − 0.1 × 0.6) = 67.68
        $this->assertEqualsWithDelta(67.68, $result->after['districts'][1]['traffic'], 0.001);
    }

    public function test_duplicate_items_count_as_repeats(): void
    {
        $lights = $this->action('lights', 10_000_000, [new EffectDef('traffic', -10)]);
        $items = [new PlannedItem($lights, districtId: 1), new PlannedItem($lights, districtId: 1)];

        $result = $this->service()->simulate($this->city(), $items, 100_000_000);

        $this->assertEqualsWithDelta(67.68, $result->after['districts'][1]['traffic'], 0.001);
    }

    public function test_spill_reaches_only_neighbors(): void
    {
        $green = $this->action('green', 18_000_000, [new EffectDef('traffic', -10, 0.5)]);

        $result = $this->service()->simulate($this->city(), [new PlannedItem($green, districtId: 1)], 100_000_000);

        $this->assertEqualsWithDelta(57.0, $result->after['districts'][2]['traffic'], 0.001); // 60 × (1 − 0.05)
        $this->assertEqualsWithDelta(80.0, $result->after['districts'][3]['traffic'], 0.001);
    }

    public function test_values_are_clamped_to_metric_range(): void
    {
        $bad = $this->action('bad', 1, [new EffectDef('heat', 50)]);

        $result = $this->service()->simulate($this->city(), [new PlannedItem($bad, districtId: 1)], 100_000_000);

        $this->assertSame(100.0, $result->after['districts'][1]['heat']);
    }

    public function test_budget_is_enforced(): void
    {
        $lane = $this->action('lane', 70_000_000);

        try {
            $this->service()->simulate($this->city(), [new PlannedItem($lane, districtId: 1, quantity: 2)], 100_000_000);
            $this->fail('Expected BudgetExceededException');
        } catch (BudgetExceededException $e) {
            $this->assertSame(40_000_000, $e->over);
        }
    }

    public function test_budget_not_enforced_reports_over_budget(): void
    {
        $lane = $this->action('lane', 70_000_000);

        $result = $this->service()->simulate($this->city(), [new PlannedItem($lane, districtId: 1, quantity: 2)], 100_000_000, enforceBudget: false);

        $this->assertTrue($result->overBudget());
        $this->assertTrue($result->toArray()['over_budget']);
    }

    public function test_city_value_is_population_weighted(): void
    {
        $result = $this->service()->simulate($this->city(), [], 100_000_000);

        // (80×1000 + 60×3000 + 80×1000) / 5000 = 68
        $this->assertEqualsWithDelta(68.0, $result->before['city']['traffic'], 0.001);
    }

    public function test_satisfaction_follows_weighted_improvements(): void
    {
        $lights = $this->action('lights', 10_000_000, [new EffectDef('traffic', -10)]);

        $result = $this->service()->simulate($this->city(), [new PlannedItem($lights, districtId: 1)], 100_000_000);

        // traffic −8 п., вес 0.3, lower is better → +2.4
        $this->assertEqualsWithDelta(52.4, $result->after['districts'][1]['satisfaction'], 0.001);
    }

    public function test_coupling_uses_change_in_share_of_source_scale(): void
    {
        $dust = $this->action('dust', 25_000_000, [new EffectDef('air', 50)]);
        $city = $this->city([new Coupling('air', 'traffic', -0.35)]);

        $result = $this->service()->simulate($city, [new PlannedItem($dust, districtId: 1)], 100_000_000);

        // air 40 → 60: Δ20 / (100 − 0) = 0.2; traffic 80 × (1 − 0.35 × 0.2) = 74.4
        $this->assertEqualsWithDelta(74.4, $result->after['districts'][1]['traffic'], 0.001);
    }

    public function test_coupling_works_from_zero_baseline(): void
    {
        $dust = $this->action('dust', 25_000_000, [new EffectDef('air', 50)]);
        $city = $this->city([new Coupling('air', 'traffic', -0.35)]);
        $zeroAir = new CityState(
            [1 => $this->district(1, 43.65, 51.15, ['traffic' => 80.0, 'air' => 0.0, 'heat' => 70.0, 'satisfaction' => 50.0])],
            $city->metrics, $city->couplings, [],
        );

        $result = $this->service()->simulate($zeroAir, [new PlannedItem($dust, districtId: 1)], 100_000_000);

        $this->assertEqualsWithDelta(80.0, $result->after['districts'][1]['traffic'], 0.001); // 0 × 1.5 = 0, без ошибки
    }

    public function test_route_adds_stops_and_raises_coverage(): void
    {
        $routeAction = $this->action('new_bus_route', 42_000_000, [], 'route');
        $route = new RouteDef(1, 'b', 'Маршрут Б', [[43.650, 51.150]], [1]);
        $city = $this->city(withCoverage: true);

        $result = $this->service()->simulate($city, [new PlannedItem($routeAction, route: $route)], 100_000_000);

        $this->assertSame(0.0, $result->before['districts'][1]['transit_coverage']);
        $this->assertGreaterThan(70.0, $result->after['districts'][1]['transit_coverage']);
        $this->assertSame(0.0, $result->after['districts'][3]['transit_coverage']);
    }

    public function test_route_action_effects_apply_to_served_districts(): void
    {
        $routeAction = $this->action('new_bus_route', 42_000_000, [new EffectDef('traffic', -10)], 'route');
        $route = new RouteDef(1, 'b', 'Маршрут Б', [[43.650, 51.150]], [1]);

        $result = $this->service()->simulate($this->city(), [new PlannedItem($routeAction, route: $route)], 100_000_000);

        $this->assertEqualsWithDelta(72.0, $result->after['districts'][1]['traffic'], 0.001);
        $this->assertEqualsWithDelta(80.0, $result->after['districts'][3]['traffic'], 0.001);
    }

    public function test_assumptions_list_used_actions(): void
    {
        $lights = $this->action('lights', 10_000_000, [new EffectDef('traffic', -10)]);

        $result = $this->service()->simulate($this->city([new Coupling('air', 'traffic', -0.35)]), [new PlannedItem($lights, districtId: 1)], 100_000_000);

        $this->assertSame('lights', $result->assumptions['actions'][0]['key']);
        $this->assertSame('допущение lights', $result->assumptions['actions'][0]['assumption']);
        $this->assertSame(['source' => 'air', 'target' => 'traffic', 'factor' => -0.35], $result->assumptions['couplings'][0]);
    }
}
