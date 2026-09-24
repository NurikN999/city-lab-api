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
}
