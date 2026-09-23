<?php

namespace App\Simulation;

final readonly class Goal
{
    public function __construct(public string $metric, public string $direction, public float $weight = 1.0) {}
}
