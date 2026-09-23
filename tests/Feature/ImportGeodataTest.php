<?php

namespace Tests\Feature;

use App\Models\BusRoute;
use App\Models\District;
use Database\Seeders\DemoCitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ImportGeodataTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoCitySeeder::class);
        $this->dir = sys_get_temp_dir().'/geodata-'.uniqid();
        File::ensureDirectoryExists($this->dir);

        $ring = [[51.160, 43.650], [51.170, 43.650], [51.170, 43.658], [51.160, 43.658], [51.160, 43.650]];
        File::put("{$this->dir}/districts.geojson", json_encode(['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'properties' => ['name' => '12 мкр', 'population' => 21000],
                'geometry' => ['type' => 'Polygon', 'coordinates' => [$ring]]],
        ]]));
        File::put("{$this->dir}/stops.json", json_encode(['elements' => [
            ['type' => 'node', 'lat' => 43.651, 'lon' => 51.161, 'tags' => ['name' => 'Остановка 1']],
            ['type' => 'node', 'lat' => 43.700, 'lon' => 51.250, 'tags' => []],
        ]]));
        File::put("{$this->dir}/routes.geojson", json_encode(['type' => 'FeatureCollection', 'features' => [
            ['type' => 'Feature', 'properties' => ['key' => 'b', 'name' => 'Маршрут Б'],
                'geometry' => ['type' => 'LineString', 'coordinates' => [[51.150, 43.640], [51.165, 43.654], [51.180, 43.660]]]],
        ]]));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_imports_geometry_and_keeps_metrics(): void
    {
        $this->artisan('city:import-geodata', ['dir' => $this->dir])->assertSuccessful();

        $twelve = District::where('name', '12 мкр')->firstOrFail();
        $this->assertSame(21000, $twelve->population);
        $this->assertEqualsWithDelta(51.165, $twelve->center_lng, 0.001);
        $this->assertSame(8, $twelve->metrics()->count()); // metric values preserved

        $this->assertDatabaseCount('stops', 2);
        $this->assertDatabaseHas('stops', ['name' => 'Остановка 1']);

        $b = BusRoute::where('key', 'b')->firstOrFail();
        $this->assertCount(3, $b->stops);
        $this->assertContains($twelve->id, $b->districts()->pluck('districts.id')->all());
    }

    public function test_missing_file_fails(): void
    {
        File::delete("{$this->dir}/stops.json");

        $this->artisan('city:import-geodata', ['dir' => $this->dir])->assertFailed();
    }
}
