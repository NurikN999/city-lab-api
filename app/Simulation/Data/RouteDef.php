<?php

namespace App\Simulation\Data;

final readonly class RouteDef
{
    /**
     * @param  list<array{0: float, 1: float}>  $stops  [lat, lng]
     * @param  list<int>  $districtIds
     */
    public function __construct(
        public int $id,
        public string $key,
        public string $name,
        public array $stops,
        public array $districtIds,
    ) {}
}
