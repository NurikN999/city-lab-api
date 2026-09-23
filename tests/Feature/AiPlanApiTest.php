<?php

namespace Tests\Feature;

use Database\Seeders\DemoCitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiPlanApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoCitySeeder::class);
        config(['services.openai.key' => null]); // fallback path, no network
    }

    public function test_plan_returns_up_to_three_saved_scenarios(): void
    {
        $response = $this->postJson('/api/ai/plan', [
            'prompt' => 'Уменьши пробки в 12 мкр и не забудь про жару, бюджет 100 млн',
        ])->assertOk()
            ->assertJsonPath('intent.district_name', '12 мкр')
            ->assertJsonPath('intent.fallback', true)
            ->assertJsonPath('intent.budget', 100_000_000)
            ->assertJsonPath('intent.goals.0.metric', 'traffic');

        $scenarios = $response->json('scenarios');
        $this->assertGreaterThanOrEqual(1, count($scenarios));
        $this->assertLessThanOrEqual(3, count($scenarios));
        $this->assertSame('A', $scenarios[0]['label']);
        foreach ($scenarios as $s) {
            $this->assertLessThanOrEqual(100_000_000, $s['scenario']['cost']);
            $this->assertSame('ai', $s['scenario']['source']);
        }
        $this->assertGreaterThan(0, $response->json('stats.combinations'));
        $this->assertNotEmpty($response->json('explanation'));
        $this->assertDatabaseCount('scenarios', count($scenarios));
    }

    public function test_unknown_district_is_422(): void
    {
        $this->postJson('/api/ai/plan', ['prompt' => 'Уменьши пробки в городе'])
            ->assertStatus(422)->assertJsonStructure(['message']);
    }

    public function test_prompt_is_validated(): void
    {
        $this->postJson('/api/ai/plan', ['prompt' => ''])->assertStatus(422)->assertJsonValidationErrors('prompt');
    }

    public function test_plan_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/ai/plan', ['prompt' => 'пробки в городе'])->assertStatus(422);
        }

        $this->postJson('/api/ai/plan', ['prompt' => 'пробки в городе'])->assertStatus(429);
    }
}
