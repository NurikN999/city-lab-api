<?php

namespace Tests\Feature;

use App\Models\Action;
use App\Models\District;
use App\Models\Metric;
use App\Models\Scenario;
use App\Models\Sphere;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    private function makeMetric(): Metric
    {
        $sphere = Sphere::create(['key' => 'transport', 'name' => 'Транспорт']);

        return Metric::create([
            'key' => 'traffic', 'name' => 'Загрузка дорог', 'unit' => '%', 'sphere_id' => $sphere->id,
            'lower_is_better' => true, 'min_value' => 0, 'max_value' => 100,
            'satisfaction_weight' => 0.3, 'is_computed' => false,
        ]);
    }

    private function makeDistrict(): District
    {
        return District::create([
            'name' => '12 мкр', 'population' => 10000, 'center_lat' => 43.65, 'center_lng' => 51.16,
            'boundary' => ['type' => 'Polygon', 'coordinates' => [[[51.15, 43.64], [51.17, 43.64], [51.17, 43.66], [51.15, 43.64]]]],
        ]);
    }

    public function test_district_metric_values_are_a_pivot_with_value(): void
    {
        $metric = $this->makeMetric();
        $district = $this->makeDistrict();

        $district->metrics()->attach($metric->id, ['value' => 84]);

        $this->assertSame(84.0, (float) $district->metrics()->first()->pivot->value);
        $this->assertSame('Polygon', $district->fresh()->boundary['type']);
    }

    public function test_one_value_per_district_and_metric(): void
    {
        $metric = $this->makeMetric();
        $district = $this->makeDistrict();
        $district->metrics()->attach($metric->id, ['value' => 84]);

        $this->expectException(QueryException::class);
        $district->metrics()->attach($metric->id, ['value' => 50]);
    }

    public function test_deleting_scenario_cascades_items(): void
    {
        $metric = $this->makeMetric();
        $district = $this->makeDistrict();
        $action = Action::create([
            'key' => 'smart_lights', 'name' => 'Умные светофоры', 'sphere_id' => $metric->sphere_id,
            'cost' => 10_000_000, 'scope' => 'district', 'assumption' => 'демо',
        ]);
        $scenario = Scenario::create(['name' => 'Тест', 'source' => 'manual', 'budget' => 100_000_000]);
        $scenario->items()->create(['action_id' => $action->id, 'district_id' => $district->id, 'quantity' => 1]);

        $scenario->delete();

        $this->assertDatabaseCount('scenario_items', 0);
    }
}
