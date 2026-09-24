<?php

namespace Tests\Feature;

use App\Geodata\OsrmClient;
use App\Geodata\RouteWriter;
use App\Models\District;
use Database\Seeders\DemoCitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OsrmClientTest extends TestCase
{
    use RefreshDatabase;

    private const POINTS = [[43.6601, 51.1601], [43.6699, 51.1699]];

    private function fakeOsrm(): void
    {
        Http::fake(['router.project-osrm.org/*' => Http::response([
            'code' => 'Ok',
            'routes' => [['geometry' => ['coordinates' => [[51.1602, 43.6602], [51.165, 43.665], [51.1698, 43.6698]]]]],
            'waypoints' => [['location' => [51.1602, 43.6602]], ['location' => [51.1698, 43.6698]]],
        ])]);
    }

    public function test_snaps_points_to_roads(): void
    {
        $this->fakeOsrm();

        $routed = app(OsrmClient::class)->route(self::POINTS);

        $this->assertTrue($routed['snapped']);
        $this->assertCount(3, $routed['path']);
        $this->assertSame([43.6602, 51.1602], $routed['stops'][0]);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/route/v1/driving/51.1601,43.6601;51.1699,43.6699')
            && str_contains($request->url(), 'geometries=geojson'));
    }

    public function test_falls_back_to_straight_lines(): void
    {
        Http::fake(['router.project-osrm.org/*' => Http::response('down', 503)]);

        $routed = app(OsrmClient::class)->route(self::POINTS);

        $this->assertFalse($routed['snapped']);
        $this->assertSame([[51.1601, 43.6601], [51.1699, 43.6699]], $routed['path']);
        $this->assertSame(self::POINTS, $routed['stops']);
    }

    public function test_caches_answers(): void
    {
        $this->fakeOsrm();

        app(OsrmClient::class)->route(self::POINTS);
        app(OsrmClient::class)->route(self::POINTS);

        Http::assertSentCount(1);
    }

    public function test_route_writer_saves_line_stops_and_districts(): void
    {
        $this->seed(DemoCitySeeder::class);
        $twelve = District::where('name', '12 мкр')->firstOrFail();

        $route = RouteWriter::save('u-test', 'Мой маршрут', [
            'path' => [[51.1, 43.6], [51.2, 43.7]],
            'stops' => [[$twelve->center_lat, $twelve->center_lng], [43.7, 51.25]],
            'snapped' => true,
        ]);

        $this->assertSame('LineString', $route->path['type']);
        $this->assertCount(2, $route->stops);
        $this->assertContains($twelve->id, $route->districts->pluck('id')->all());
    }
}
