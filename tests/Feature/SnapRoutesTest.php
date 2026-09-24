<?php

namespace Tests\Feature;

use App\Models\BusRoute;
use Database\Seeders\DemoCitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SnapRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoCitySeeder::class);
    }

    public function test_redraws_routes_along_roads(): void
    {
        Http::fake(['router.project-osrm.org/*' => fn ($request) => Http::response([
            'code' => 'Ok',
            'routes' => [['geometry' => ['coordinates' => array_fill(0, 40, [51.15, 43.66])]]],
            'waypoints' => array_fill(0, substr_count($request->url(), ';') + 1, ['location' => [51.15, 43.66]]),
        ])]);

        $this->artisan('city:snap-routes b')->assertSuccessful()->expectsOutputToContain('Маршрут Б перестроен по дорогам');

        $route = BusRoute::where('key', 'b')->firstOrFail();
        $this->assertCount(40, $route->path['coordinates']);
        $this->assertSame('Маршрут Б', $route->name);
    }

    public function test_keeps_route_when_osrm_is_down(): void
    {
        Http::fake(['router.project-osrm.org/*' => Http::response('down', 503)]);
        $before = BusRoute::where('key', 'b')->firstOrFail()->path;

        $this->artisan('city:snap-routes b')->assertFailed();

        $this->assertSame($before, BusRoute::where('key', 'b')->firstOrFail()->path);
    }

    public function test_skips_a_route_with_a_single_stop(): void
    {
        Http::fake();
        $route = BusRoute::where('key', 'c')->firstOrFail();
        $route->stops()->where('position', '>', 0)->delete();

        $this->artisan('city:snap-routes c')->assertSuccessful()->expectsOutputToContain('меньше двух остановок');

        Http::assertNothingSent();
    }
}
