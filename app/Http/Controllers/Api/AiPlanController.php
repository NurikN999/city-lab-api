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
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AiPlanController extends Controller
{
    private const LABELS = ['A', 'B', 'C'];

    public function __invoke(AiPlanRequest $request, CityStateRepository $repo, CityAiService $ai, ScenarioOptimizer $optimizer): JsonResponse
    {
        $districts = $repo->districtIdsByName();
        $metricNames = Metric::orderBy('id')->pluck('name', 'key')->all();
        $intent = $ai->parse($request->validated('prompt'), $districts, $metricNames, config('simulation.default_budget'));
        if ($intent === null) {
            return response()->json(['message' => 'Не удалось определить район. Укажите его, например: «12 мкр».'], 422);
        }

        $city = $repo->load();
        $actions = $repo->actions();
        $optimized = $optimizer->optimize(
            $city,
            $intent->districtId,
            array_values(array_filter($actions, fn ($a) => $a->scope === 'district')),
            collect($actions)->first(fn ($a) => $a->scope === 'route'),
            array_values($repo->routes()),
            $intent->goals,
            $intent->budget,
        );

        $out = [];
        $labeled = [];
        DB::transaction(function () use ($optimized, $repo, $intent, &$out, &$labeled) {
            foreach ($optimized->scenarios as $i => $candidate) {
                $label = self::LABELS[$i];
                $name = implode(' + ', array_map(
                    fn (PlannedItem $it) => $it->route !== null ? $it->route->name : $it->action->name,
                    $candidate->items,
                ));
                $scenario = $repo->create(Str::limit("{$label} · {$name}", 240, '…'), 'ai', $intent->budget, $intent->districtId, $candidate->items);
                $labeled[$label] = ['name' => $name, 'result' => $candidate->result];
                $out[] = ['label' => $label, 'scenario' => new ScenarioResource($scenario), 'result' => $candidate->result->toArray()];
            }
        });

        return response()->json([
            'intent' => [
                'district_id' => $intent->districtId,
                'district_name' => array_search($intent->districtId, $districts, true),
                'goals' => array_map(fn (Goal $g) => ['metric' => $g->metric, 'direction' => $g->direction, 'weight' => $g->weight], $intent->goals),
                'budget' => $intent->budget,
                'fallback' => $intent->fromFallback,
            ],
            'stats' => ['combinations' => $optimized->combinations, 'within_budget' => $optimized->withinBudget],
            'scenarios' => $out,
            'explanation' => $out === []
                ? 'Не нашлось сочетаний действий в пределах бюджета, которые улучшают цель.'
                : $ai->explain(ComparisonSummary::build($labeled, $intent->districtId, $metricNames)),
        ]);
    }
}
