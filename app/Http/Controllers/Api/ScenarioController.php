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

        $result = $simulation->simulate($repo->load(), $items, $budget); // BudgetExceededException → 422
        $scenario = $repo->create($request->validated('name'), 'manual', $budget, $request->validated('district_id'), $items);

        return response()->json(['scenario' => new ScenarioResource($scenario), 'result' => $result->toArray()], 201);
    }

    public function show(Scenario $scenario, CityStateRepository $repo, SimulationService $simulation): JsonResponse
    {
        $scenario->load('items.action');
        $result = $simulation->simulate($repo->load(), $repo->itemsOf($scenario), $scenario->budget, enforceBudget: false);

        return response()->json(['scenario' => new ScenarioResource($scenario), 'result' => $result->toArray()]);
    }
}
