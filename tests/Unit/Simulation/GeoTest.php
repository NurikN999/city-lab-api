<?php

namespace Tests\Unit\Simulation;

use App\Simulation\Geo;
use PHPUnit\Framework\TestCase;

class GeoTest extends TestCase
{
    private const SQUARE = [[51.0, 43.0], [51.1, 43.0], [51.1, 43.1], [51.0, 43.1], [51.0, 43.0]];

    public function test_distance_in_aktau_longitude_step(): void
    {
        // 0.01° долготы на широте 43.65 ≈ 804 м
        $this->assertEqualsWithDelta(804.6, Geo::distanceM(43.65, 51.16, 43.65, 51.17), 2.0);
    }

    public function test_distance_one_degree_latitude(): void
    {
        $this->assertEqualsWithDelta(111_195, Geo::distanceM(43.0, 51.0, 44.0, 51.0), 50);
    }

    public function test_contains(): void
    {
        $this->assertTrue(Geo::contains(self::SQUARE, 43.05, 51.05));
        $this->assertFalse(Geo::contains(self::SQUARE, 43.2, 51.05));
    }

    public function test_grid_of_square_is_full(): void
    {
        $this->assertCount(144, Geo::grid(self::SQUARE, 12));
    }

    public function test_grid_of_triangle_is_partial(): void
    {
        $triangle = [[51.0, 43.0], [51.1, 43.0], [51.0, 43.1], [51.0, 43.0]];
        $count = count(Geo::grid($triangle, 12));

        $this->assertGreaterThan(50, $count);
        $this->assertLessThan(100, $count);
    }

    public function test_area_of_a_small_square_in_hectares(): void
    {
        // 0.01° × 0.01° у широты Актау ≈ 805 м × 1105 м ≈ 89 га
        $ring = [[51.16, 43.65], [51.17, 43.65], [51.17, 43.66], [51.16, 43.66], [51.16, 43.65]];

        $this->assertEqualsWithDelta(89.0, Geo::areaHa($ring), 1.0);
    }

    public function test_area_of_a_degenerate_ring_is_zero(): void
    {
        $this->assertSame(0.0, Geo::areaHa([]));
        $this->assertSame(0.0, Geo::areaHa([[51.16, 43.65], [51.17, 43.65]]));
    }
}
