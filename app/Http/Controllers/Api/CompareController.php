<?php

namespace App\Http\Controllers\Api;

use App\CityAi\CityAiService;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompareRequest;
use App\Http\Resources\ScenarioResource;
use App\Models\Metric;
use App\Models\Scenario;
use App\Simulation\CityStateRepository;
use App\Simulation\ComparisonSummary;
use App\Simulation\SimulationService;
use Illuminate\Http\JsonResponse;

class CompareController extends Controller
{
    public function __invoke(CompareRequest $request, CityStateRepository $repo, SimulationService $simulation, CityAiService $ai): JsonResponse
    {
        $scenarios = collect($request->ids())->map(fn (int $id) => Scenario::with('items.action')->findOrFail($id));
        $city = $repo->load();
        $districtIds = $scenarios->pluck('district_id')->unique();
        $focus = $districtIds->count() === 1 ? $districtIds->first() : null;

        $out = [];
        $labeled = [];
        foreach ($scenarios->values() as $i => $scenario) {
            $label = chr(ord('A') + $i);
            $result = $simulation->simulate($city, $repo->itemsOf($scenario), $scenario->budget, enforceBudget: false);
            $labeled[$label] = ['name' => $scenario->name, 'result' => $result];
            $out[] = ['label' => $label, 'scenario' => new ScenarioResource($scenario), 'result' => $result->toArray()];
        }

        return response()->json([
            'scenarios' => $out,
            'explanation' => $ai->explain(ComparisonSummary::build($labeled, $focus, Metric::pluck('name', 'key')->all())),
        ]);
    }
}
