<?php

namespace Tests\Feature;

use App\Models\Action;
use App\Models\BusRoute;
use App\Models\District;
use Database\Seeders\DemoCitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScenarioApiTest extends TestCase
{
    use RefreshDatabase;

    private int $twelve;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoCitySeeder::class);
        $this->twelve = District::where('name', '12 мкр')->value('id');
    }

    private function action(string $key): int
    {
        return Action::where('key', $key)->value('id');
    }

    public function test_store_simulates_and_saves(): void
    {
        $response = $this->postJson('/api/scenarios', [
            'name' => 'Транспорт + тень',
            'district_id' => $this->twelve,
            'items' => [
                ['action_id' => $this->action('new_bus_route'), 'route_id' => BusRoute::where('key', 'b')->value('id')],
                ['action_id' => $this->action('smart_lights'), 'district_id' => $this->twelve],
                ['action_id' => $this->action('greening'), 'district_id' => $this->twelve],
            ],
        ])->assertCreated();

        $this->assertSame(70_000_000, $response->json('scenario.cost'));
        $this->assertSame(100_000_000, $response->json('scenario.budget'));
        $this->assertCount(3, $response->json('scenario.items'));
        $this->assertLessThan(
            $response->json("result.before.districts.{$this->twelve}.traffic"),
            $response->json("result.after.districts.{$this->twelve}.traffic"),
        );
        $this->assertDatabaseCount('scenarios', 1);
        $this->assertDatabaseCount('scenario_items', 3);
    }

    public function test_store_rejects_over_budget_and_saves_nothing(): void
    {
        $this->postJson('/api/scenarios', [
            'name' => 'Дорого',
            'budget' => 20_000_000,
            'items' => [['action_id' => $this->action('bus_lane'), 'district_id' => $this->twelve]],
        ])->assertStatus(422)
            ->assertJsonPath('error', 'budget_exceeded')
            ->assertJsonPath('over', 10_000_000);

        $this->assertDatabaseCount('scenarios', 0);
    }

    public function test_store_validates_scope(): void
    {
        $this->postJson('/api/scenarios', [
            'name' => 'Ошибка',
            'items' => [['action_id' => $this->action('new_bus_route'), 'district_id' => $this->twelve]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.route_id');

        $this->postJson('/api/scenarios', [
            'name' => 'Ошибка',
            'items' => [['action_id' => $this->action('smart_lights')]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.district_id');
    }

    public function test_duplicate_items_have_diminishing_return(): void
    {
        $item = ['action_id' => $this->action('smart_lights'), 'district_id' => $this->twelve];

        $response = $this->postJson('/api/scenarios', ['name' => 'Дважды', 'items' => [$item, $item]])->assertCreated();

        // 84 × 0.94 × (1 − 0.06 × 0.6) = 76.1
        $this->assertEqualsWithDelta(76.1, $response->json("result.after.districts.{$this->twelve}.traffic"), 0.15);
    }

    public function test_index_and_show(): void
    {
        $id = $this->postJson('/api/scenarios', [
            'name' => 'Светофоры',
            'items' => [['action_id' => $this->action('smart_lights'), 'district_id' => $this->twelve]],
        ])->json('scenario.id');

        $this->getJson('/api/scenarios')->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', 'Светофоры');
        $this->getJson("/api/scenarios/{$id}")->assertOk()
            ->assertJsonPath('scenario.items.0.action_key', 'smart_lights')
            ->assertJsonPath('result.over_budget', false);
    }
}
