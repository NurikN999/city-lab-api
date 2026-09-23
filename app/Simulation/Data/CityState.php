<?php

namespace App\Simulation\Data;

final readonly class CityState
{
    /**
     * @param  array<int, DistrictState>  $districts  keyed by id
     * @param  array<string, MetricDef>  $metrics  keyed by key
     * @param  list<Coupling>  $couplings  in application order
     * @param  list<array{0: float, 1: float}>  $stops  existing stops [lat, lng]
     */
    public function __construct(
        public array $districts,
        public array $metrics,
        public array $couplings,
        public array $stops,
    ) {}
}
