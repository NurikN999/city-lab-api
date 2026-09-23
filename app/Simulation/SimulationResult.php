<?php

namespace App\Simulation;

final readonly class SimulationResult
{
    /**
     * @param  array{city: array<string, float>, districts: array<int, array<string, float>>}  $before
     * @param  array{city: array<string, float>, districts: array<int, array<string, float>>}  $after
     * @param  array{actions: list<array>, couplings: list<array>}  $assumptions
     */
    public function __construct(
        public array $before,
        public array $after,
        public int $cost,
        public int $budget,
        public array $assumptions,
    ) {}

    public function delta(int $districtId, string $metric): float
    {
        return ($this->after['districts'][$districtId][$metric] ?? 0.0) - ($this->before['districts'][$districtId][$metric] ?? 0.0);
    }

    public function overBudget(): bool
    {
        return $this->cost > $this->budget;
    }

    public function toArray(): array
    {
        $round = fn (array $values) => array_map(fn (float $v) => round($v, 1), $values);
        $side = fn (array $s) => ['city' => $round($s['city']), 'districts' => array_map($round, $s['districts'])];

        return [
            'before' => $side($this->before),
            'after' => $side($this->after),
            'cost' => $this->cost,
            'budget' => $this->budget,
            'over_budget' => $this->overBudget(),
            'assumptions' => $this->assumptions,
        ];
    }
}
