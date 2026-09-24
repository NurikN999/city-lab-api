<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScenarioRequest;
use App\Http\Resources\ScenarioResource;
use App\Models\Scenario;
use App\Simulation\CityStateRepository;
use App\Simulation\SimulationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ScenarioController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ScenarioResource::collection(Scenario::with('items.action')->latest('id')->limit(50)->get());
    }

    public function store(StoreScenarioRequest $request, CityStateRepository $repo, SimulationService $simulation): JsonResponse
    {
        $items = $repo->plannedItems($request->validated('items'));
        $budget = $request->validated('budget') ?? config('simulation.default_budget');

        $city = $repo->load();
        $result = $simulation->simulate($city, $items, $budget); // BudgetExceededException → 422
        $districtId = $request->validated('district_id');
        $scenario = $repo->create($request->validated('name'), 'manual', $budget, $districtId, $items);

        return response()->json([
            'scenario' => new ScenarioResource($scenario),
            'result' => $result->toArray(),
            'contributions' => $districtId === null ? [] : $simulation->contributions($city, $items, $districtId),
        ], 201);
    }

    public function show(Scenario $scenario, CityStateRepository $repo, SimulationService $simulation): JsonResponse
    {
        $scenario->load('items.action');
        $city = $repo->load();
        $items = $repo->itemsOf($scenario);
        $result = $simulation->simulate($city, $items, $scenario->budget, enforceBudget: false);

        return response()->json([
            'scenario' => new ScenarioResource($scenario),
            'result' => $result->toArray(),
            'contributions' => $scenario->district_id === null ? [] : $simulation->contributions($city, $items, $scenario->district_id),
        ]);
    }
}
