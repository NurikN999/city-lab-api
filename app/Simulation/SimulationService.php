<?php

namespace App\Simulation;

use App\Simulation\Data\CityState;
use App\Simulation\Data\DistrictState;
use App\Simulation\Data\EffectDef;
use App\Simulation\Data\PlannedItem;

final class SimulationService
{
    /** @var array<string, float> per-request memo: city object + district + stop-set key → coverage */
    private array $coverageCache = [];

    /** @var array<string, list<int>> */
    private array $neighborCache = [];

    /** @param array{diminishing_factor: float, neighbor_radius_m: float, stop_access_radius_m: float, coverage_grid: int} $config */
    public function __construct(private array $config) {}

    /** @param list<PlannedItem> $items */
    public function simulate(CityState $city, array $items, int $budget, bool $enforceBudget = true): SimulationResult
    {
        $cost = array_sum(array_map(fn (PlannedItem $i) => $i->action->cost * $i->quantity, $items));
        if ($enforceBudget && $cost > $budget) {
            throw new BudgetExceededException($cost - $budget);
        }

        $before = $this->baseline($city);
        $after = $before;

        $this->applyRouteCoverage($city, $items, $after);
        $this->applyEffects($city, $items, $after);
        $this->applyCouplings($city, $before, $after);
        $this->clamp($city, $after);
        $this->applySatisfaction($city, $before, $after);

        return new SimulationResult(
            ['city' => $this->cityAverage($city, $before), 'districts' => $before],
            ['city' => $this->cityAverage($city, $after), 'districts' => $after],
            $cost,
            $budget,
            $this->assumptions($city, $items),
        );
    }

    /** @return array<int, array<string, float>> */
    private function baseline(CityState $city): array
    {
        $out = [];
        foreach ($city->districts as $id => $district) {
            $out[$id] = $district->values;
            if (isset($city->metrics['transit_coverage'])) {
                $out[$id]['transit_coverage'] = $this->coverage($city, $district, $city->stops, 'base');
            }
        }

        return $out;
    }

    /** @param list<PlannedItem> $items */
    private function applyRouteCoverage(CityState $city, array $items, array &$after): void
    {
        $routeStops = [];
        $routeIds = [];
        foreach ($items as $item) {
            if ($item->route !== null) {
                array_push($routeStops, ...$item->route->stops);
                $routeIds[] = $item->route->id;
            }
        }
        if ($routeStops === [] || ! isset($city->metrics['transit_coverage'])) {
            return;
        }

        $stops = array_merge($city->stops, $routeStops);
        sort($routeIds);
        $setKey = 'routes:'.implode(',', $routeIds);
        foreach ($city->districts as $id => $district) {
            $after[$id]['transit_coverage'] = $this->coverage($city, $district, $stops, $setKey);
        }
    }

    /** @param list<PlannedItem> $items */
    private function applyEffects(CityState $city, array $items, array &$after): void
    {
        $applied = [];
        foreach ($items as $item) {
            $targets = $item->route !== null ? $item->route->districtIds : [$item->districtId];
            foreach ($targets as $districtId) {
                if (! isset($city->districts[$districtId])) {
                    continue;
                }
                for ($q = 0; $q < $item->quantity; $q++) {
                    $key = $item->action->key.':'.$districtId;
                    $k = $applied[$key] = ($applied[$key] ?? 0) + 1;
                    $decay = $this->config['diminishing_factor'] ** ($k - 1);
                    foreach ($item->action->effects as $effect) {
                        $this->applyEffect($city, $after, $districtId, $effect, $decay);
                    }
                }
            }
        }
    }

    private function applyEffect(CityState $city, array &$after, int $districtId, EffectDef $effect, float $decay): void
    {
        $factor = $effect->deltaPct / 100 * $decay;
        if (isset($after[$districtId][$effect->metric])) {
            $after[$districtId][$effect->metric] *= 1 + $factor;
        }
        if ($effect->spill <= 0) {
            return;
        }
        foreach ($this->neighbors($city, $districtId) as $neighborId) {
            if (isset($after[$neighborId][$effect->metric])) {
                $after[$neighborId][$effect->metric] *= 1 + $factor * $effect->spill;
            }
        }
    }

    private function applyCouplings(CityState $city, array $before, array &$after): void
    {
        foreach ($city->couplings as $coupling) {
            $source = $city->metrics[$coupling->source] ?? null;
            $range = $source ? $source->max - $source->min : 0.0;
            if ($range <= 0) {
                continue;
            }
            foreach ($after as $id => $values) {
                if (! isset($values[$coupling->source], $values[$coupling->target], $before[$id][$coupling->source])) {
                    continue;
                }
                $share = ($values[$coupling->source] - $before[$id][$coupling->source]) / $range;
                $after[$id][$coupling->target] *= 1 + $coupling->factor * $share;
            }
        }
    }

    private function clamp(CityState $city, array &$after): void
    {
        foreach ($after as $id => $values) {
            foreach ($values as $key => $value) {
                $metric = $city->metrics[$key] ?? null;
                if ($metric !== null) {
                    $after[$id][$key] = max($metric->min, min($metric->max, $value));
                }
            }
        }
    }

    private function applySatisfaction(CityState $city, array $before, array &$after): void
    {
        $satisfaction = $city->metrics['satisfaction'] ?? null;
        if ($satisfaction === null) {
            return;
        }
        foreach ($after as $id => $values) {
            if (! isset($before[$id]['satisfaction'])) {
                continue;
            }
            $gain = 0.0;
            foreach ($city->metrics as $key => $metric) {
                if ($metric->satisfactionWeight <= 0 || ! isset($before[$id][$key], $values[$key])) {
                    continue;
                }
                $diff = $values[$key] - $before[$id][$key];
                $gain += $metric->satisfactionWeight * ($metric->lowerIsBetter ? -$diff : $diff);
            }
            $after[$id]['satisfaction'] = max($satisfaction->min, min($satisfaction->max, $before[$id]['satisfaction'] + $gain));
        }
    }

    /** @return array<string, float> */
    private function cityAverage(CityState $city, array $districtValues): array
    {
        $out = [];
        foreach (array_keys($city->metrics) as $key) {
            $sum = 0.0;
            $weight = 0;
            foreach ($city->districts as $id => $district) {
                if (isset($districtValues[$id][$key])) {
                    $sum += $districtValues[$id][$key] * $district->population;
                    $weight += $district->population;
                }
            }
            if ($weight > 0) {
                $out[$key] = $sum / $weight;
            }
        }

        return $out;
    }

    /** @return list<int> */
    private function neighbors(CityState $city, int $districtId): array
    {
        $cacheKey = spl_object_id($city).':'.$districtId;
        if (isset($this->neighborCache[$cacheKey])) {
            return $this->neighborCache[$cacheKey];
        }
        $origin = $city->districts[$districtId];
        $ids = [];
        foreach ($city->districts as $other) {
            if ($other->id !== $districtId
                && Geo::distanceM($origin->lat, $origin->lng, $other->lat, $other->lng) <= $this->config['neighbor_radius_m']) {
                $ids[] = $other->id;
            }
        }

        return $this->neighborCache[$cacheKey] = $ids;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $stops
     * @param  string  $setKey  identifies the stop set cheaply ('base' or sorted route ids) — never hash the stops themselves
     */
    private function coverage(CityState $city, DistrictState $district, array $stops, string $setKey): float
    {
        $key = spl_object_id($city).':'.$district->id.':'.$setKey;

        return $this->coverageCache[$key] ??= (new CoverageCalculator(
            $this->config['stop_access_radius_m'],
            $this->config['coverage_grid'],
        ))->percent($district->ring, $stops);
    }

    /** @param list<PlannedItem> $items */
    private function assumptions(CityState $city, array $items): array
    {
        $actions = [];
        foreach ($items as $item) {
            $actions[$item->action->key] ??= [
                'key' => $item->action->key,
                'name' => $item->action->name,
                'assumption' => $item->action->assumption,
                'source_url' => $item->action->sourceUrl,
            ];
        }

        return [
            'actions' => array_values($actions),
            'couplings' => array_map(fn ($c) => ['source' => $c->source, 'target' => $c->target, 'factor' => $c->factor], $city->couplings),
        ];
    }
}
