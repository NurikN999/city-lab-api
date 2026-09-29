<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PreviewScenarioRequest;
use App\Http\Requests\StoreScenarioRequest;
use App\Http\Resources\ScenarioResource;
use App\Models\Complaint;
use App\Models\Scenario;
use App\Simulation\CityStateRepository;
use App\Simulation\Data\CityState;
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
        // Жалобы, которые решает сценарий, — «приняты»; закрытые и скрытые не открываем заново
        Complaint::whereIn('id', $request->validated('complaint_ids', []))
            ->whereIn('status', Complaint::ACTIVE)
            ->update(['status' => 'accepted', 'scenario_id' => $scenario->id]);

        return response()->json([
            'scenario' => new ScenarioResource($scenario),
            'result' => $result->toArray(),
            'contributions' => $this->contributions($simulation, $city, $items, $districtId),
        ], 201);
    }

    /** Пересчёт без сохранения — конструктор зовёт его, пока объект тащат по карте. */
    public function preview(PreviewScenarioRequest $request, CityStateRepository $repo, SimulationService $simulation): JsonResponse
    {
        $items = $repo->plannedItems($request->validated('items'));
        $budget = $request->validated('budget') ?? config('simulation.default_budget');

        return response()->json(['result' => $simulation->simulate($repo->load(), $items, $budget, enforceBudget: false)->toArray()]);
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
            'contributions' => $this->contributions($simulation, $city, $items, $scenario->district_id),
        ]);
    }

    /** Пустые deltas (снос без эффекта) отдаём объектом {}: json_encode превратил бы [] в список. */
    private function contributions(SimulationService $simulation, CityState $city, array $items, ?int $districtId): array
    {
        if ($districtId === null) {
            return [];
        }

        return array_map(fn (array $c) => [...$c, 'deltas' => (object) $c['deltas']], $simulation->contributions($city, $items, $districtId));
    }
}
