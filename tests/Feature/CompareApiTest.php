<?php

namespace Tests\Feature;

use App\Models\Action;
use App\Models\District;
use Database\Seeders\DemoCitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompareApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoCitySeeder::class);
        config(['services.openai.key' => null]);
    }

    private function scenario(string $action): int
    {
        $twelve = District::where('name', '12 мкр')->value('id');

        return $this->postJson('/api/scenarios', [
            'name' => $action,
            'district_id' => $twelve,
            'items' => [['action_id' => Action::where('key', $action)->value('id'), 'district_id' => $twelve]],
        ])->json('scenario.id');
    }

    public function test_compare_two_scenarios(): void
    {
        $a = $this->scenario('smart_lights');
        $b = $this->scenario('greening');

        $response = $this->getJson("/api/compare?ids={$a},{$b}")->assertOk()
            ->assertJsonCount(2, 'scenarios')
            ->assertJsonPath('scenarios.0.label', 'A')
            ->assertJsonPath('scenarios.1.scenario.id', $b);

        $this->assertStringContainsString('стоимость', $response->json('explanation'));
    }

    public function test_compare_is_rate_limited(): void
    {
        $a = $this->scenario('smart_lights');
        for ($i = 0; $i < 30; $i++) {
            $this->getJson("/api/compare?ids={$a}")->assertOk();
        }

        $this->getJson("/api/compare?ids={$a}")->assertStatus(429);
    }

    public function test_compare_validates_ids(): void
    {
        $this->getJson('/api/compare?ids=1,2,3,4')->assertStatus(422);
        $this->getJson('/api/compare?ids=abc')->assertStatus(422);
        $this->getJson('/api/compare?ids=999')->assertNotFound();
    }
}
