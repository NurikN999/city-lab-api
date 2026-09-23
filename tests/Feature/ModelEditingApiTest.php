<?php

namespace Tests\Feature;

use App\Models\Action;
use App\Models\District;
use App\Models\User;
use Database\Seeders\DemoCitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ModelEditingApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoCitySeeder::class);
        User::where('email', 'akimat@citylab.kz')->update(['password' => Hash::make('secret-pass')]);
        $this->token = $this->postJson('/api/login', ['email' => 'akimat@citylab.kz', 'password' => 'secret-pass'])
            ->assertOk()->json('token');
    }

    private function lights(): Action
    {
        return Action::where('key', 'smart_lights')->firstOrFail();
    }

    public function test_bad_password(): void
    {
        $this->postJson('/api/login', ['email' => 'akimat@citylab.kz', 'password' => 'wrong'])
            ->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_editing_requires_token(): void
    {
        $this->putJson("/api/actions/{$this->lights()->id}", ['cost' => 1, 'effects' => []])->assertUnauthorized();
    }

    public function test_update_action_changes_simulation(): void
    {
        $twelve = District::where('name', '12 мкр')->value('id');
        $scenarioId = $this->postJson('/api/scenarios', [
            'name' => 'Светофоры',
            'items' => [['action_id' => $this->lights()->id, 'district_id' => $twelve]],
        ])->json('scenario.id');

        $this->withToken($this->token)->putJson("/api/actions/{$this->lights()->id}", [
            'cost' => 12_000_000,
            'effects' => [['metric' => 'traffic', 'delta_pct' => -20, 'spill' => 0]],
        ])->assertOk()->assertJsonPath('cost', 12_000_000)->assertJsonCount(1, 'effects');

        // 84 × 0.8 = 67.2, затем связь traffic → co2 не влияет на traffic
        $this->assertEqualsWithDelta(67.2, $this->getJson("/api/scenarios/{$scenarioId}")->json("result.after.districts.{$twelve}.traffic"), 0.1);
    }

    public function test_scenario_over_budget_after_edit_still_reads(): void
    {
        $twelve = District::where('name', '12 мкр')->value('id');
        $scenarioId = $this->postJson('/api/scenarios', [
            'name' => 'Впритык',
            'budget' => 10_000_000,
            'items' => [['action_id' => $this->lights()->id, 'district_id' => $twelve]],
        ])->assertCreated()->json('scenario.id');

        $this->withToken($this->token)->putJson("/api/actions/{$this->lights()->id}", [
            'cost' => 20_000_000,
            'effects' => [['metric' => 'traffic', 'delta_pct' => -6, 'spill' => 0.3]],
        ])->assertOk();

        $this->getJson("/api/scenarios/{$scenarioId}")->assertOk()->assertJsonPath('result.over_budget', true);
        $this->getJson("/api/compare?ids={$scenarioId}")->assertOk();
    }

    public function test_action_cannot_target_computed_metric(): void
    {
        $this->withToken($this->token)->putJson("/api/actions/{$this->lights()->id}", [
            'cost' => 10_000_000,
            'effects' => [['metric' => 'transit_coverage', 'delta_pct' => 10, 'spill' => 0]],
        ])->assertStatus(422)->assertJsonValidationErrors('effects.0.metric');
    }

    public function test_update_district_values(): void
    {
        $twelve = District::where('name', '12 мкр')->firstOrFail();

        $this->withToken($this->token)->putJson("/api/districts/{$twelve->id}", [
            'population' => 18_000,
            'values' => ['traffic' => 90],
        ])->assertOk()->assertJsonPath('population', 18_000)->assertJsonPath('values.traffic', 90);

        $this->withToken($this->token)->putJson("/api/districts/{$twelve->id}", ['values' => ['transit_coverage' => 50]])
            ->assertStatus(422)->assertJsonValidationErrors('values.transit_coverage');
        $this->withToken($this->token)->putJson("/api/districts/{$twelve->id}", ['values' => ['traffic' => 150]])
            ->assertStatus(422)->assertJsonValidationErrors('values.traffic');
    }
}
