<?php

namespace App\Http\Controllers\Api;

use App\CityAi\CityAiService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiPlanRequest;
use App\Http\Resources\ScenarioResource;
use App\Models\Metric;
use App\Simulation\CityStateRepository;
use App\Simulation\ComparisonSummary;
use App\Simulation\Data\PlannedItem;
use App\Simulation\Goal;
use App\Simulation\ScenarioOptimizer;
use App\Simulation\SimulationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AiPlanController extends Controller
{
    private const LABELS = ['A', 'B', 'C'];

    public function __invoke(AiPlanRequest $request, CityStateRepository $repo, CityAiService $ai, ScenarioOptimizer $optimizer, SimulationService $simulation): JsonResponse
    {
        $districts = $repo->districtIdsByName();
        $metricNames = Metric::orderBy('id')->pluck('name', 'key')->all();
        $intent = $ai->parse($request->validated('prompt'), $districts, $metricNames, config('simulation.default_budget'));
        if ($intent === null) {
            return response()->json(['message' => 'Не понял запрос. Назовите цель и, если нужно, район: «уменьши пробки в 12 мкр, бюджет 100 млн».'], 422);
        }

        $city = $repo->load();
        $districtId = $intent->districtId ?? $this->worstDistrict($simulation->simulate($city, [], 0)->before['districts'], $intent->goals[0]);
        $actions = $repo->actions();
        $optimized = $optimizer->optimize(
            $city,
            $districtId,
            array_values(array_filter($actions, fn ($a) => $a->scope === 'district')),
            collect($actions)->first(fn ($a) => $a->scope === 'route'),
            array_values(array_filter($repo->routes(), fn ($r) => ! str_starts_with($r->key, 'u-'))), // только подготовленные маршруты
            $intent->goals,
            $intent->budget,
        );

        $out = [];
        $labeled = [];
        DB::transaction(function () use ($optimized, $repo, $intent, $districtId, &$out, &$labeled) {
            foreach ($optimized->scenarios as $i => $candidate) {
                $label = self::LABELS[$i];
                $name = implode(' + ', array_map(
                    fn (PlannedItem $it) => $it->route !== null ? $it->route->name : $it->action->name,
                    $candidate->items,
                ));
                $scenario = $repo->create(Str::limit("{$label} · {$name}", 240, '…'), 'ai', $intent->budget, $districtId, $candidate->items);
                $labeled[$label] = ['name' => $name, 'result' => $candidate->result];
                $out[] = ['label' => $label, 'scenario' => new ScenarioResource($scenario), 'result' => $candidate->result->toArray()];
            }
        });

        return response()->json([
            'intent' => [
                'district_id' => $districtId,
                'district_name' => array_search($districtId, $districts, true),
                'district_auto' => $intent->districtId === null,
                'goals' => array_map(fn (Goal $g) => ['metric' => $g->metric, 'direction' => $g->direction, 'weight' => $g->weight], $intent->goals),
                'budget' => $intent->budget,
                'fallback' => $intent->fromFallback,
            ],
            'stats' => ['combinations' => $optimized->combinations, 'within_budget' => $optimized->withinBudget],
            'scenarios' => $out,
            'explanation' => $out === []
                ? 'Не нашлось сочетаний действий в пределах бюджета, которые улучшают цель.'
                : $ai->explain(ComparisonSummary::build($labeled, $districtId, $metricNames)),
        ]);
    }

    /** Самый проблемный район по главной цели: максимум для «уменьшить», минимум для «увеличить». */
    private function worstDistrict(array $values, Goal $goal): int
    {
        $byDistrict = array_map(fn (array $v) => $v[$goal->metric] ?? 0.0, $values);

        return (int) array_search($goal->direction === 'decrease' ? max($byDistrict) : min($byDistrict), $byDistrict, true);
    }
}
