<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDistrictRequest;
use App\Models\District;
use App\Models\Metric;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DistrictController extends Controller
{
    public function __invoke(UpdateDistrictRequest $request, District $district): JsonResponse
    {
        $metricIds = Metric::pluck('id', 'key');
        DB::transaction(function () use ($request, $district, $metricIds) {
            if ($request->has('population')) {
                $district->update(['population' => (int) $request->validated('population')]);
            }
            $district->metrics()->syncWithoutDetaching(collect($request->validated('values', []))
                ->mapWithKeys(fn ($value, $key) => [$metricIds[$key] => ['value' => $value]])
                ->all());
        });

        $district->refresh()->load('metrics');

        return response()->json([
            'id' => $district->id,
            'population' => $district->population,
            'values' => $district->metrics->mapWithKeys(fn (Metric $m) => [$m->key => (float) $m->pivot->value]),
        ]);
    }
}
