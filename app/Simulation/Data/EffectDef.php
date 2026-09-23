<?php

namespace App\Simulation\Data;

final readonly class EffectDef
{
    public function __construct(public string $metric, public float $deltaPct, public float $spill = 0.0) {}
}
