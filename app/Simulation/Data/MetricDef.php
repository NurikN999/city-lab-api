<?php

namespace App\Simulation\Data;

final readonly class MetricDef
{
    public function __construct(
        public string $key,
        public bool $lowerIsBetter,
        public float $min,
        public float $max,
        public float $satisfactionWeight = 0.0,
        public bool $isComputed = false,
    ) {}
}
