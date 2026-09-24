<?php

namespace Tests\Feature;

use App\Geodata\RouteWriter;
use App\Models\District;
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

    public function test_unknown_district_or_no_goal_is_422(): void
    {
        $this->postJson('/api/ai/plan', ['prompt' => 'Уменьши пробки в 99 мкр'])->assertStatus(422)->assertJsonStructure(['message']);
        $this->postJson('/api/ai/plan', ['prompt' => 'Привет, что умеешь?'])->assertStatus(422)->assertJsonStructure(['message']);
    }

    public function test_rate_limit_is_per_client_behind_proxy(): void
    {
        $proxy = ['REMOTE_ADDR' => '10.0.0.1'];
        for ($i = 0; $i < 10; $i++) {
            $this->withServerVariables($proxy)->withHeader('X-Forwarded-For', '1.1.1.1')
                ->postJson('/api/ai/plan', ['prompt' => 'Привет'])->assertStatus(422);
        }

        $this->withServerVariables($proxy)->withHeader('X-Forwarded-For', '2.2.2.2')
            ->postJson('/api/ai/plan', ['prompt' => 'Привет'])->assertStatus(422);
    }

    public function test_long_generated_names_fit_varchar_255(): void
    {
        \App\Models\BusRoute::where('key', 'b')->update(['name' => 'Маршрут Б: '.str_repeat('Приморский бульвар — ', 8)]);

        $response = $this->postJson('/api/ai/plan', ['prompt' => 'пробки жара воздух вода школ автобус в 12 мкр бюджет 1 млрд'])->assertOk();

        foreach ($response->json('scenarios') as $s) {
            $this->assertLessThanOrEqual(255, mb_strlen($s['scenario']['name']));
        }
    }

    public function test_prompt_is_validated(): void
    {
        $this->postJson('/api/ai/plan', ['prompt' => ''])->assertStatus(422)->assertJsonValidationErrors('prompt');
    }

    public function test_plan_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/ai/plan', ['prompt' => 'Привет'])->assertStatus(422);
        }

        $this->postJson('/api/ai/plan', ['prompt' => 'Привет'])->assertStatus(429);
    }

    public function test_plan_ignores_user_drawn_routes(): void
    {
        $d = District::where('name', '12 мкр')->firstOrFail();
        $stops = [[$d->center_lat, $d->center_lng], [$d->center_lat + 0.001, $d->center_lng]];
        RouteWriter::save('u-spam', 'SPAM', ['path' => [[$d->center_lng, $d->center_lat]], 'stops' => $stops, 'snapped' => true]);

        $response = $this->postJson('/api/ai/plan', ['prompt' => 'Уменьши пробки в 12 мкр'])->assertOk();

        foreach ($response->json('scenarios') as $s) {
            $this->assertStringNotContainsString('SPAM', $s['scenario']['name']);
        }
    }

    public function test_plan_without_district_picks_the_worst_district_for_the_goal(): void
    {
        $worst = \App\Models\District::query()
            ->join('district_metric_values as v', 'v.district_id', '=', 'districts.id')
            ->join('metrics as m', 'm.id', '=', 'v.metric_id')
            ->where('m.key', 'traffic')->orderByDesc('v.value')->value('districts.name');

        $this->postJson('/api/ai/plan', ['prompt' => '100 млн, уменьшить пробки'])->assertOk()
            ->assertJsonPath('intent.district_auto', true)
            ->assertJsonPath('intent.district_name', $worst);
    }
}
