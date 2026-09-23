<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateActionRequest;
use App\Models\Action;
use App\Models\ActionEffect;
use App\Models\Metric;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ActionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Action::with(['sphere', 'effects.metric'])->orderBy('id')->get()->map(fn (Action $a) => $this->present($a))
        );
    }

    public function update(UpdateActionRequest $request, Action $action): JsonResponse
    {
        $metricIds = Metric::pluck('id', 'key');
        DB::transaction(function () use ($request, $action, $metricIds) {
            $action->update(['cost' => (int) $request->validated('cost')]);
            $action->effects()->delete();
            foreach ($request->validated('effects') as $effect) {
                $action->effects()->create([
                    'metric_id' => $metricIds[$effect['metric']],
                    'delta_pct' => $effect['delta_pct'],
                    'spill' => $effect['spill'],
                ]);
            }
        });

        return response()->json($this->present($action->fresh(['sphere', 'effects.metric'])));
    }

    protected function present(Action $action): array
    {
        return [
            'id' => $action->id,
            'key' => $action->key,
            'name' => $action->name,
            'sphere' => ['key' => $action->sphere->key, 'name' => $action->sphere->name],
            'cost' => $action->cost,
            'scope' => $action->scope,
            'assumption' => $action->assumption,
            'source_url' => $action->source_url,
            'effects' => $action->effects->map(fn (ActionEffect $e) => [
                'metric' => $e->metric->key, 'delta_pct' => $e->delta_pct, 'spill' => $e->spill,
            ])->all(),
        ];
    }
}
