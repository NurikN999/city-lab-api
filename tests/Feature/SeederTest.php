<?php

namespace Tests\Feature;

use App\Models\District;
use Database\Seeders\DemoCitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_city_is_seeded(): void
    {
        $this->seed(DemoCitySeeder::class);

        $this->assertDatabaseCount('spheres', 5);
        $this->assertDatabaseCount('metrics', 9);
        $this->assertDatabaseCount('districts', 16);
        $this->assertDatabaseCount('district_metric_values', 16 * 8); // transit_coverage вычисляется
        $this->assertDatabaseCount('stops', 12);
        $this->assertDatabaseCount('routes', 3);
        $this->assertDatabaseCount('route_stops', 18);
        $this->assertDatabaseCount('district_route', 16);
        $this->assertDatabaseCount('actions', 10);
        $this->assertDatabaseCount('action_effects', 13);
        $this->assertDatabaseCount('metric_couplings', 2);
        $this->assertDatabaseHas('users', ['email' => 'akimat@citylab.kz']);

        $twelve = District::where('name', '12 мкр')->firstOrFail();
        $traffic = $twelve->metrics()->where('key', 'traffic')->first();
        $this->assertSame(84.0, (float) $traffic->pivot->value);
    }
}
