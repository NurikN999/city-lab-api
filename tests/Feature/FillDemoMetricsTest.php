<?php

namespace Tests\Feature;

use App\Models\District;
use Database\Seeders\DemoCitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FillDemoMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_fills_only_missing_values(): void
    {
        $this->seed(DemoCitySeeder::class);
        $new = District::create([
            'name' => '1А мкр', 'population' => 10000, 'center_lat' => 43.63, 'center_lng' => 51.18,
            'boundary' => ['type' => 'Polygon', 'coordinates' => [[[51.17, 43.62], [51.19, 43.62], [51.19, 43.64], [51.17, 43.62]]]],
        ]);

        $this->artisan('city:fill-demo-metrics')->assertSuccessful()->expectsOutputToContain('1 район');

        $this->assertSame(8, $new->metrics()->count());
        $this->assertDatabaseMissing('district_metric_values', ['district_id' => $new->id, 'metric_id' => \App\Models\Metric::where('key', 'transit_coverage')->value('id')]);
        $twelve = District::where('name', '12 мкр')->firstOrFail();
        $this->assertSame(84.0, (float) $twelve->metrics()->where('key', 'traffic')->first()->pivot->value);
    }

    public function test_is_idempotent(): void
    {
        $this->seed(DemoCitySeeder::class);

        $this->artisan('city:fill-demo-metrics')->assertSuccessful()->expectsOutputToContain('0 районов');

        $this->assertDatabaseCount('district_metric_values', 16 * 8);
    }

    public function test_estimates_missing_population_from_area(): void
    {
        $this->seed(DemoCitySeeder::class);
        config(['simulation.demo_density_per_ha' => 100]);
        $square = ['type' => 'Polygon', 'coordinates' => [[[51.16, 43.65], [51.17, 43.65], [51.17, 43.66], [51.16, 43.66], [51.16, 43.65]]]];
        $empty = District::create(['name' => '1А мкр', 'population' => 0, 'center_lat' => 43.655, 'center_lng' => 51.165, 'boundary' => $square]);

        $this->artisan('city:fill-demo-metrics')->assertSuccessful()->expectsOutputToContain('Население оценено: 1');

        $this->assertEqualsWithDelta(8900, $empty->fresh()->population, 100);
        $this->assertSame(10000, District::where('name', '12 мкр')->value('population'));
    }

    public function test_population_flag_reestimates_every_district(): void
    {
        $this->seed(DemoCitySeeder::class);

        $this->artisan('city:fill-demo-metrics --population')->assertSuccessful()->expectsOutputToContain('Население оценено: 16');

        $this->assertNotSame(10000, District::where('name', '12 мкр')->value('population'));
    }

    public function test_missing_density_setting_falls_back_to_default(): void
    {
        $this->seed(DemoCitySeeder::class);
        config(['simulation.demo_density_per_ha' => null]); // устаревший config:cache без нового ключа

        $this->artisan('city:fill-demo-metrics --population')->assertSuccessful();

        $this->assertGreaterThan(0, District::where('name', '12 мкр')->value('population'));
    }
}
