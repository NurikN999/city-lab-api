<?php

namespace Tests\Feature;

use Database\Seeders\DemoCitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemoCitySeeder::class);
    }

    public function test_city(): void
    {
        $response = $this->getJson('/api/city')->assertOk()
            ->assertJsonCount(16, 'districts')
            ->assertJsonCount(9, 'metrics')
            ->assertJsonCount(5, 'spheres')
            ->assertJsonStructure(['districts' => [['id', 'name', 'population', 'center' => ['lat', 'lng'], 'boundary', 'values']], 'city']);

        $twelve = collect($response->json('districts'))->firstWhere('name', '12 мкр');
        $this->assertEquals(84, $twelve['values']['traffic']);
        $this->assertEquals(0, $twelve['values']['transit_coverage']);
        $this->assertArrayHasKey('traffic', $response->json('city'));
    }

    public function test_actions(): void
    {
        $response = $this->getJson('/api/actions')->assertOk()->assertJsonCount(10);

        $lights = collect($response->json())->firstWhere('key', 'smart_lights');
        $this->assertSame(10_000_000, $lights['cost']);
        $this->assertSame('transport', $lights['sphere']['key']);
        $this->assertEquals(['metric' => 'traffic', 'delta_pct' => -6, 'spill' => 0.3], $lights['effects'][0]);
    }

    public function test_routes(): void
    {
        $response = $this->getJson('/api/routes')->assertOk()->assertJsonCount(3);

        $b = collect($response->json())->firstWhere('key', 'b');
        $this->assertCount(7, $b['stops']);
        $this->assertSame('LineString', $b['path']['type']);
        $this->assertCount(7, $b['district_ids']);
    }

    public function test_model(): void
    {
        $this->getJson('/api/model')->assertOk()
            ->assertJsonPath('couplings.0', ['source' => 'transit_coverage', 'target' => 'traffic', 'factor' => -0.35])
            ->assertJsonPath('constants.default_budget', 100_000_000);
    }
}
