<?php

namespace App\Simulation;

use App\Simulation\Data\ActionDef;
use App\Simulation\Data\CityState;
use App\Simulation\Data\PlannedItem;
use App\Simulation\Data\RouteDef;

final class ScenarioOptimizer
{
    public function __construct(private SimulationService $simulation) {}

    /**
     * ponytail: brute force over relevant subsets (≤ 2^n × routes); fine for ~10 actions, switch to greedy/knapsack beyond ~15.
     *
     * @param  list<ActionDef>  $districtActions
     * @param  list<RouteDef>  $routes
     * @param  list<Goal>  $goals
     */
    public function optimize(
        CityState $city,
        int $districtId,
        array $districtActions,
        ?ActionDef $routeAction,
        array $routes,
        array $goals,
        int $budget,
        int $limit = 3,
    ): OptimizationResult {
        $relevant = $this->relevantMetrics($city, $goals);
        $actions = array_values(array_filter(
            $districtActions,
            fn (ActionDef $a) => array_intersect(array_map(fn ($e) => $e->metric, $a->effects), $relevant) !== [],
        ));
        $routeOptions = [null];
        if ($routeAction !== null && in_array('transit_coverage', $relevant, true)) {
            foreach ($routes as $route) {
                if (in_array($districtId, $route->districtIds, true)) {
                    $routeOptions[] = $route;
                }
            }
        }

        $candidates = [];
        $combinations = 0;
        $withinBudget = 0;
        $n = count($actions);
        foreach ($routeOptions as $route) {
            for ($mask = 0; $mask < (1 << $n); $mask++) {
                if ($mask === 0 && $route === null) {
                    continue;
                }
                $combinations++;
                $items = $route !== null ? [new PlannedItem($routeAction, route: $route)] : [];
                for ($i = 0; $i < $n; $i++) {
                    if ($mask & (1 << $i)) {
                        $items[] = new PlannedItem($actions[$i], districtId: $districtId);
                    }
                }
                if (array_sum(array_map(fn (PlannedItem $it) => $it->action->cost, $items)) > $budget) {
                    continue;
                }
                $withinBudget++;
                $result = $this->simulation->simulate($city, $items, $budget);
                $score = $this->score($result, $districtId, $goals);
                if ($score > 0) {
                    $candidates[] = new OptimizedScenario($items, $result, $score);
                }
            }
        }

        usort($candidates, fn (OptimizedScenario $a, OptimizedScenario $b) => [$b->score, $a->result->cost] <=> [$a->score, $b->result->cost]);

        $picked = [];
        foreach ($candidates as $candidate) {
            if (count($picked) >= $limit) {
                break;
            }
            foreach ($picked as $existing) {
                if ($this->jaccard($candidate->keys(), $existing->keys()) >= 0.67) {
                    continue 2;
                }
            }
            $picked[] = $candidate;
        }

        return new OptimizationResult($picked, $combinations, $withinBudget);
    }

    /** @return list<string> goal metrics plus every metric that feeds them through couplings */
    private function relevantMetrics(CityState $city, array $goals): array
    {
        $set = array_map(fn (Goal $g) => $g->metric, $goals);
        do {
            $added = false;
            foreach ($city->couplings as $c) {
                if (in_array($c->target, $set, true) && ! in_array($c->source, $set, true)) {
                    $set[] = $c->source;
                    $added = true;
                }
            }
        } while ($added);

        return $set;
    }

    /** @param list<Goal> $goals */
    private function score(SimulationResult $result, int $districtId, array $goals): float
    {
        $score = 0.0;
        foreach ($goals as $goal) {
            $delta = $result->delta($districtId, $goal->metric);
            $score += $goal->weight * ($goal->direction === 'decrease' ? -$delta : $delta);
        }

        return $score;
    }

    private function jaccard(array $a, array $b): float
    {
        $union = count(array_unique([...$a, ...$b]));

        return $union === 0 ? 1.0 : count(array_intersect($a, $b)) / $union;
    }
}
