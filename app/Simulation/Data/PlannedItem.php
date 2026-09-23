<?php

namespace App\Simulation\Data;

final readonly class PlannedItem
{
    public function __construct(
        public ActionDef $action,
        public ?int $districtId = null,
        public ?RouteDef $route = null,
        public int $quantity = 1,
    ) {}
}
