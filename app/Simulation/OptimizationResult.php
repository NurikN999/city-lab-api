<?php

namespace App\Simulation;

final readonly class OptimizationResult
{
    /** @param list<OptimizedScenario> $scenarios */
    public function __construct(public array $scenarios, public int $combinations, public int $withinBudget) {}
}
