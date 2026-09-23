<?php

namespace App\Simulation;

use App\Models\Action;
use App\Models\ActionEffect;
use App\Models\BusRoute;
use App\Models\District;
use App\Models\Metric;
use App\Models\MetricCoupling;
use App\Models\RouteStop;
use App\Models\Scenario;
use App\Models\ScenarioItem;
use App\Models\Stop;
use App\Simulation\Data\ActionDef;
use App\Simulation\Data\CityState;
use App\Simulation\Data\Coupling;
use App\Simulation\Data\DistrictState;
use App\Simulation\Data\EffectDef;
use App\Simulation\Data\MetricDef;
use App\Simulation\Data\PlannedItem;
use App\Simulation\Data\RouteDef;
use Illuminate\Support\Facades\DB;

final class CityStateRepository
{
    public function load(): CityState
    {
        $metrics = Metric::orderBy('id')->get();
        $keysById = $metrics->pluck('key', 'id')->all();
        $values = DB::table('district_metric_values')->get()->groupBy('district_id');

        $districts = District::orderBy('id')->get()->mapWithKeys(fn (District $d) => [$d->id => new DistrictState(
            $d->id,
            $d->name,
            $d->population,
            $d->center_lat,
            $d->center_lng,
            $d->boundary['coordinates'][0],
            ($values[$d->id] ?? collect())->mapWithKeys(fn ($row) => [$keysById[$row->metric_id] => (float) $row->value])->all(),
        )])->all();

        return new CityState(
            $districts,
            $metrics->mapWithKeys(fn (Metric $m) => [$m->key => new MetricDef(
                $m->key, $m->lower_is_better, $m->min_value, $m->max_value, $m->satisfaction_weight, $m->is_computed,
            )])->all(),
            MetricCoupling::orderBy('id')->get()->map(fn (MetricCoupling $c) => new Coupling(
                $keysById[$c->source_metric_id], $keysById[$c->target_metric_id], $c->factor,
            ))->all(),
            Stop::orderBy('id')->get()->map(fn (Stop $s) => [$s->lat, $s->lng])->all(),
        );
    }

    /** @return array<int, ActionDef> */
    public function actions(): array
    {
        return Action::with('effects.metric')->orderBy('id')->get()->mapWithKeys(fn (Action $a) => [$a->id => new ActionDef(
            $a->id,
            $a->key,
            $a->name,
            $a->cost,
            $a->scope,
            $a->effects->map(fn (ActionEffect $e) => new EffectDef($e->metric->key, $e->delta_pct, $e->spill))->all(),
            $a->assumption,
            $a->source_url,
        )])->all();
    }

    /** @return array<int, RouteDef> */
    public function routes(): array
    {
        return BusRoute::with(['stops', 'districts:id'])->orderBy('id')->get()->mapWithKeys(fn (BusRoute $r) => [$r->id => new RouteDef(
            $r->id,
            $r->key,
            $r->name,
            $r->stops->map(fn (RouteStop $s) => [$s->lat, $s->lng])->all(),
            $r->districts->pluck('id')->all(),
        )])->all();
    }

    /**
     * @param  list<array{action_id: int, district_id?: ?int, route_id?: ?int, quantity?: ?int}>  $rows
     * @return list<PlannedItem>
     */
    public function plannedItems(array $rows): array
    {
        $actions = $this->actions();
        $routes = $this->routes();

        return array_values(array_map(fn (array $row) => new PlannedItem(
            $actions[$row['action_id']],
            $row['district_id'] ?? null,
            isset($row['route_id']) ? $routes[$row['route_id']] : null,
            (int) ($row['quantity'] ?? 1),
        ), $rows));
    }

    /** @return list<PlannedItem> */
    public function itemsOf(Scenario $scenario): array
    {
        return $this->plannedItems($scenario->items->map(
            fn (ScenarioItem $i) => $i->only(['action_id', 'district_id', 'route_id', 'quantity'])
        )->all());
    }

    /** @param list<PlannedItem> $items */
    public function create(string $name, string $source, int $budget, ?int $districtId, array $items): Scenario
    {
        return DB::transaction(function () use ($name, $source, $budget, $districtId, $items) {
            $scenario = Scenario::create([
                'name' => $name, 'source' => $source, 'budget' => $budget, 'district_id' => $districtId,
            ]);
            foreach ($items as $item) {
                $scenario->items()->create([
                    'action_id' => $item->action->id,
                    'district_id' => $item->districtId,
                    'route_id' => $item->route?->id,
                    'quantity' => $item->quantity,
                ]);
            }

            return $scenario->load('items.action');
        });
    }

    /** @return array<string, int> */
    public function districtIdsByName(): array
    {
        return District::pluck('id', 'name')->all();
    }
}
