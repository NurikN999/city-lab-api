<?php

namespace App\Simulation\Data;

final readonly class Coupling
{
    public function __construct(public string $source, public string $target, public float $factor) {}
}
