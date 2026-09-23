<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Action;
use App\Models\ActionEffect;
use Illuminate\Http\JsonResponse;

class ActionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Action::with(['sphere', 'effects.metric'])->orderBy('id')->get()->map(fn (Action $a) => $this->present($a))
        );
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
