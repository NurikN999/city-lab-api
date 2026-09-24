<?php

namespace App\CityAi;

use App\Simulation\Goal;

final class KeywordIntentParser
{
    /** metric => [direction, stems]; order = priority for the main goal */
    private const METRICS = [
        'traffic' => ['decrease', ['пробк', 'трафик', 'дорог', 'загруж']],
        'heat' => ['decrease', ['жар', 'тень', 'зной']],
        'air' => ['increase', ['воздух', 'пыль', 'кошкар']],
        'water_loss' => ['decrease', ['вод', 'утечк', 'труб']],
        'social_access' => ['increase', ['школ', 'поликлин', 'врач', 'соцобъект']],
        'transit_coverage' => ['increase', ['остановк', 'автобус']],
    ];

    /** @param array<string, int> $districtIdsByNumber '12' | '12а' => id */
    public function parse(string $prompt, array $districtIdsByNumber, int $defaultBudget): ?Intent
    {
        $text = mb_strtolower($prompt);
        if (! preg_match('/(\d+)\s*((?!й)[а-яё])?\s*(?:-?й\s*)?(?:мкр|микрорайон)/u', $text, $district)
            || ! isset($districtIdsByNumber[$district[1].($district[2] ?? '')])) {
            return null;
        }
        $districtKey = $district[1].($district[2] ?? '');

        $goals = [];
        foreach (self::METRICS as $metric => [$direction, $stems]) {
            foreach ($stems as $stem) {
                if (str_contains($text, $stem)) {
                    $goals[] = new Goal($metric, $direction, $goals === [] ? 1.0 : 0.5);
                    break;
                }
            }
        }
        if ($goals === []) {
            $goals[] = new Goal('traffic', 'decrease');
        }

        $budget = $defaultBudget;
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(млрд|млн)/u', $text, $money)) {
            $amount = (float) str_replace(',', '.', $money[1]);
            $budget = (int) round($amount * ($money[2] === 'млрд' ? 1_000_000_000 : 1_000_000));
        }

        return new Intent($districtIdsByNumber[$districtKey], $goals, $budget, true);
    }
}
