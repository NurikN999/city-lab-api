<?php

namespace Tests\Unit\Geodata;

use App\Geodata\DemoMetrics;
use PHPUnit\Framework\TestCase;

class DemoMetricsTest extends TestCase
{
    public function test_values_follow_the_seeder_formula(): void
    {
        $this->assertEquals([
            'traffic' => 84, 'travel_time' => 100, 'co2' => 100, 'heat' => 76.0, 'air' => 58.0,
            'water_loss' => 30, 'social_access' => 66, 'satisfaction' => 49.0,
        ], DemoMetrics::values(84, 2, 1));
    }

    public function test_for_name_is_deterministic_and_in_range(): void
    {
        $a = DemoMetrics::forName('ЖМ Самал');

        $this->assertSame($a, DemoMetrics::forName('ЖМ Самал'));
        $this->assertGreaterThanOrEqual(35, $a['traffic']);
        $this->assertLessThanOrEqual(84, $a['traffic']);
        $this->assertCount(8, $a);
    }
}
