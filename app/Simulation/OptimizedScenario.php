<?php

namespace App\Simulation;

use App\Simulation\Data\PlannedItem;

final readonly class OptimizedScenario
{
    /** @param list<PlannedItem> $items */
    public function __construct(public array $items, public SimulationResult $result, public float $score) {}

    /** @return list<string> e.g. ['new_bus_route:b', 'smart_lights'] */
    public function keys(): array
    {
        return array_map(fn (PlannedItem $i) => $i->action->key.($i->route !== null ? ':'.$i->route->key : ''), $this->items);
    }
}
