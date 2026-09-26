<?php

namespace Tests\Feature;

use App\Models\Action;
use App\Simulation\MapObjectCatalog;
use Database\Seeders\DemoCitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapObjectCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_city_has_five_map_objects(): void
    {
        $this->seed(DemoCitySeeder::class);

        $objects = $this->getJson('/api/actions')->assertOk()->collect()->where('scope', 'point')->keyBy('key');

        $this->assertSame(['school', 'kindergarten', 'clinic', 'park', 'bus_stop'], $objects->keys()->all());
        $this->assertSame(500, $objects['school']['radius_m']);
        $this->assertSame('social_access', $objects['school']['effects'][0]['metric']);
        $this->assertSame([], $objects['bus_stop']['effects']);
    }

    public function test_adding_objects_twice_changes_nothing(): void
    {
        $this->seed(DemoCitySeeder::class);

        $this->assertSame(0, MapObjectCatalog::ensure());
        $this->assertSame(5, Action::where('scope', 'point')->count());
    }
}
