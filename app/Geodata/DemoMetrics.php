<?php

namespace App\Geodata;

/** ponytail: демо-значения метрик до получения данных акимата (spec §16). */
final class DemoMetrics
{
    /** @return array<string, int|float> */
    public static function values(int $traffic, int $waterVariant, int $socialRow): array
    {
        return [
            'traffic' => $traffic,
            'travel_time' => 100,
            'co2' => 100,
            'heat' => round(55 + 0.25 * $traffic),
            'air' => round(92 - 0.4 * $traffic),
            'water_loss' => 24 + ($waterVariant % 4) * 3,
            'social_access' => 72 - ($socialRow % 3) * 6,
            'satisfaction' => round(95 - 0.55 * $traffic),
        ];
    }

    /** @return array<string, int|float> детерминированно по названию района */
    public static function forName(string $name): array
    {
        $hash = crc32($name);

        return self::values(35 + $hash % 50, intdiv($hash, 50), intdiv($hash, 200));
    }
}
