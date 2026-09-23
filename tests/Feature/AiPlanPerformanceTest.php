<?php

namespace Tests\Feature;

use App\Models\Stop;
use Database\Seeders\DemoCitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiPlanPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_goals_plan_with_osm_sized_stop_set_is_fast(): void
    {
        $this->seed(DemoCitySeeder::class);
        config(['services.openai.key' => null]);
        mt_srand(42);
        $rows = [];
        for ($i = 0; $i < 400; $i++) {
            $rows[] = ['lat' => 43.62 + mt_rand() / mt_getrandmax() * 0.06, 'lng' => 51.10 + mt_rand() / mt_getrandmax() * 0.10];
        }
        Stop::insert($rows);

        $started = microtime(true);
        $this->postJson('/api/ai/plan', ['prompt' => 'пробки жара воздух вода школ автобус в 12 мкр'])->assertOk();
        $elapsed = microtime(true) - $started;

        // ponytail: generous wall-clock guard (≈10× headroom over the fixed path) — catches the per-call serialize() regression
        $this->assertLessThan(2.0, $elapsed, "ai/plan took {$elapsed}s");
    }
}
