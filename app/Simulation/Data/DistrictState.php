<?php

namespace App\Simulation\Data;

final readonly class DistrictState
{
    /**
     * @param  list<array{0: float, 1: float}>  $ring  GeoJSON ring [lng, lat]
     * @param  array<string, float>  $values  stored metric values
     */
    public function __construct(
        public int $id,
        public string $name,
        public int $population,
        public float $lat,
        public float $lng,
        public array $ring,
        public array $values,
    ) {}
}
