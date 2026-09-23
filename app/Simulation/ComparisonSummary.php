<?php

namespace App\Simulation;

final class ComparisonSummary
{
    /**
     * @param  array<string, array{name: string, result: SimulationResult}>  $labeled
     * @param  array<string, string>  $metricNames  key => human name
     */
    public static function build(array $labeled, ?int $districtId, array $metricNames): array
    {
        $scenarios = [];
        foreach ($labeled as $label => ['name' => $name, 'result' => $result]) {
            $before = $districtId !== null ? $result->before['districts'][$districtId] : $result->before['city'];
            $after = $districtId !== null ? $result->after['districts'][$districtId] : $result->after['city'];
            $deltas = [];
            foreach ($after as $key => $value) {
                $delta = round($value - ($before[$key] ?? $value), 1);
                if (abs($delta) >= 0.1) {
                    $deltas[$metricNames[$key] ?? $key] = $delta;
                }
            }
            $scenarios[] = ['label' => (string) $label, 'name' => $name, 'cost' => $result->cost, 'deltas' => $deltas];
        }

        return ['scope' => $districtId !== null ? 'district' : 'city', 'scenarios' => $scenarios];
    }
}
