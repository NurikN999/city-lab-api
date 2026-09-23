<?php

namespace Tests\Feature;

use App\Models\Action;
use App\Models\District;
use App\Simulation\CityStateRepository;
use App\Simulation\SimulationService;
use Database\Seeders\DemoCitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CityStateRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoCitySeeder::class);
    }

    public function test_loads_city_state(): void
    {
        $city = app(CityStateRepository::class)->load();
        $twelve = District::where('name', '12 мкр')->value('id');

        $this->assertCount(16, $city->districts);
        $this->assertCount(9, $city->metrics);
        $this->assertTrue($city->metrics['transit_coverage']->isComputed);
        $this->assertSame(84.0, $city->districts[$twelve]->values['traffic']);
        $this->assertCount(2, $city->couplings);
        $this->assertSame('transit_coverage', $city->couplings[0]->source);
        $this->assertCount(12, $city->stops);
    }

    public function test_schematic_stops_leave_district_12_uncovered(): void
    {
        $repo = app(CityStateRepository::class);
        $result = app(SimulationService::class)->simulate($repo->load(), [], 100_000_000);
        $ids = $repo->districtIdsByName();

        $this->assertSame(0.0, $result->before['districts'][$ids['12 мкр']]['transit_coverage']);
        $this->assertGreaterThan(0.0, $result->before['districts'][$ids['1 мкр']]['transit_coverage']);
    }

    public function test_route_b_reduces_traffic_in_12(): void
    {
        $repo = app(CityStateRepository::class);
        $routeB = collect($repo->routes())->first(fn ($r) => $r->key === 'b');
        $routeAction = Action::where('key', 'new_bus_route')->value('id');
        $items = $repo->plannedItems([['action_id' => $routeAction, 'route_id' => $routeB->id]]);
        $twelve = $repo->districtIdsByName()['12 мкр'];

        $result = app(SimulationService::class)->simulate($repo->load(), $items, 100_000_000);

        $this->assertGreaterThan(30.0, $result->after['districts'][$twelve]['transit_coverage']);
        $this->assertLessThan(84.0, $result->after['districts'][$twelve]['traffic']);
    }

    public function test_create_and_read_back_scenario_items(): void
    {
        $repo = app(CityStateRepository::class);
        $lights = Action::where('key', 'smart_lights')->value('id');
        $twelve = $repo->districtIdsByName()['12 мкр'];
        $items = $repo->plannedItems([['action_id' => $lights, 'district_id' => $twelve, 'quantity' => 2]]);

        $scenario = $repo->create('Тест', 'manual', 100_000_000, $twelve, $items);
        $back = $repo->itemsOf($scenario);

        $this->assertSame('smart_lights', $back[0]->action->key);
        $this->assertSame($twelve, $back[0]->districtId);
        $this->assertSame(2, $back[0]->quantity);
    }
}
