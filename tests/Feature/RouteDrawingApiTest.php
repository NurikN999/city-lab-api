<?php

namespace Tests\Feature;

use App\Models\Action;
use Database\Seeders\DemoCitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RouteDrawingApiTest extends TestCase
{
    use RefreshDatabase;

    private const POINTS = [['lat' => 43.6601, 'lng' => 51.1601], ['lat' => 43.6699, 'lng' => 51.1699]];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoCitySeeder::class);
    }

    private function fakeOsrm(): void
    {
        Http::fake(['router.project-osrm.org/*' => Http::response([
            'code' => 'Ok',
            'routes' => [['geometry' => ['coordinates' => [[51.1602, 43.6602], [51.165, 43.665], [51.1698, 43.6698]]]]],
            'waypoints' => [['location' => [51.1602, 43.6602]], ['location' => [51.1698, 43.6698]]],
        ])]);
    }

    public function test_preview_follows_roads(): void
    {
        $this->fakeOsrm();

        $this->postJson('/api/routes/preview', ['points' => self::POINTS])->assertOk()
            ->assertJsonPath('snapped', true)
            ->assertJsonPath('path.type', 'LineString')
            ->assertJsonCount(3, 'path.coordinates')
            ->assertJsonPath('stops.0', ['lat' => 43.6602, 'lng' => 51.1602])
            ->assertJsonStructure(['district_ids']);
    }

    public function test_preview_works_without_osrm(): void
    {
        Http::fake(['router.project-osrm.org/*' => Http::response('down', 503)]);

        $this->postJson('/api/routes/preview', ['points' => self::POINTS])->assertOk()
            ->assertJsonPath('snapped', false)
            ->assertJsonCount(2, 'path.coordinates');
    }

    public function test_points_outside_aktau_are_rejected(): void
    {
        $this->postJson('/api/routes/preview', ['points' => [['lat' => 51.1, 'lng' => 71.4], self::POINTS[0]]])
            ->assertStatus(422)->assertJsonValidationErrors('points.0.lat');
        $this->postJson('/api/routes/preview', ['points' => [self::POINTS[0]]])
            ->assertStatus(422)->assertJsonValidationErrors('points');
    }

    public function test_store_saves_route_that_can_be_simulated(): void
    {
        $this->fakeOsrm();

        $response = $this->postJson('/api/routes', ['name' => 'Мой маршрут', 'points' => self::POINTS])->assertCreated()
            ->assertJsonPath('name', 'Мой маршрут')
            ->assertJsonCount(2, 'stops');

        $this->assertStringStartsWith('u-', $response->json('key'));
        $this->getJson('/api/routes')->assertOk()->assertJsonCount(4);

        $this->postJson('/api/scenarios', [
            'name' => 'Свой маршрут',
            'items' => [['action_id' => Action::where('key', 'new_bus_route')->value('id'), 'route_id' => $response->json('id')]],
        ])->assertCreated();
    }

    public function test_store_requires_a_name(): void
    {
        $this->postJson('/api/routes', ['points' => self::POINTS])->assertStatus(422)->assertJsonValidationErrors('name');
    }
}
