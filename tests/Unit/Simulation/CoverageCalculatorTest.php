<?php

namespace Tests\Unit\Simulation;

use App\Simulation\CoverageCalculator;
use PHPUnit\Framework\TestCase;

class CoverageCalculatorTest extends TestCase
{
    /** Квадрат ~1 км × 1 км вокруг (43.65, 51.16). */
    private function square(): array
    {
        $dLat = 0.0045;   // ≈ 500 м
        $dLng = 0.00621;  // ≈ 500 м на широте 43.65

        return [
            [51.16 - $dLng, 43.65 - $dLat], [51.16 + $dLng, 43.65 - $dLat],
            [51.16 + $dLng, 43.65 + $dLat], [51.16 - $dLng, 43.65 + $dLat],
            [51.16 - $dLng, 43.65 - $dLat],
        ];
    }

    public function test_stop_in_center_covers_circle_share(): void
    {
        $percent = (new CoverageCalculator(500, 12))->percent($this->square(), [[43.65, 51.16]]);

        // круг r=500 м в квадрате 1×1 км ≈ π/4 ≈ 78.5%
        $this->assertEqualsWithDelta(78.5, $percent, 7.0);
    }

    public function test_no_stops_means_zero(): void
    {
        $this->assertSame(0.0, (new CoverageCalculator(500, 12))->percent($this->square(), []));
    }

    public function test_far_stop_means_zero(): void
    {
        $this->assertSame(0.0, (new CoverageCalculator(500, 12))->percent($this->square(), [[43.75, 51.30]]));
    }

    public function test_degenerate_polygon_is_zero_not_error(): void
    {
        $line = [[51.1, 43.6], [51.2, 43.6], [51.3, 43.6], [51.1, 43.6]];

        $this->assertSame(0.0, (new CoverageCalculator(500, 12))->percent($line, [[43.6, 51.2]]));
    }
}
