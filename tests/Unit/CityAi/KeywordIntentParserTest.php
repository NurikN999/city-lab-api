<?php

namespace Tests\Unit\CityAi;

use App\CityAi\KeywordIntentParser;
use PHPUnit\Framework\TestCase;

class KeywordIntentParserTest extends TestCase
{
    private const DISTRICTS = ['12' => 105, '27' => 207];

    public function test_demo_phrase(): void
    {
        $intent = (new KeywordIntentParser)->parse('Уменьши пробки в 12 мкр и не забудь про жару, бюджет 100 млн', self::DISTRICTS, 100_000_000);

        $this->assertSame(105, $intent->districtId);
        $this->assertSame(100_000_000, $intent->budget);
        $this->assertSame('traffic', $intent->goals[0]->metric);
        $this->assertSame('decrease', $intent->goals[0]->direction);
        $this->assertSame(1.0, $intent->goals[0]->weight);
        $this->assertSame('heat', $intent->goals[1]->metric);
        $this->assertSame(0.5, $intent->goals[1]->weight);
    }

    public function test_microdistrict_word_and_billions(): void
    {
        $intent = (new KeywordIntentParser)->parse('27 микрорайон: улучши воздух, есть 1,5 млрд', self::DISTRICTS, 100_000_000);

        $this->assertSame(207, $intent->districtId);
        $this->assertSame(1_500_000_000, $intent->budget);
        $this->assertSame('air', $intent->goals[0]->metric);
        $this->assertSame('increase', $intent->goals[0]->direction);
    }

    public function test_defaults_to_traffic_and_default_budget(): void
    {
        $intent = (new KeywordIntentParser)->parse('Что сделать в 12 мкр?', self::DISTRICTS, 100_000_000);

        $this->assertSame('traffic', $intent->goals[0]->metric);
        $this->assertSame(100_000_000, $intent->budget);
    }

    public function test_unknown_district_returns_null(): void
    {
        $this->assertNull((new KeywordIntentParser)->parse('Пробки в 99 мкр', self::DISTRICTS, 100_000_000));
    }

    public function test_goal_without_district_leaves_district_open(): void
    {
        $intent = (new KeywordIntentParser)->parse('100 млн, уменьшить пробки', self::DISTRICTS, 100_000_000);

        $this->assertNull($intent->districtId);
        $this->assertSame('traffic', $intent->goals[0]->metric);
        $this->assertSame(100_000_000, $intent->budget);
    }

    public function test_no_district_and_no_goal_is_not_understood(): void
    {
        $this->assertNull((new KeywordIntentParser)->parse('Привет, что умеешь?', self::DISTRICTS, 100_000_000));
    }
}
