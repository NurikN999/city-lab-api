<?php

namespace App\Http\Controllers\Api;

use App\Geodata\OsrmClient;
use App\Geodata\RouteDistricts;
use App\Geodata\RouteWriter;
use App\Http\Controllers\Controller;
use App\Http\Requests\RoutePointsRequest;
use App\Http\Requests\StoreRouteRequest;
use App\Models\BusRoute;
use App\Models\District;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class RouteController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            BusRoute::with(['stops', 'districts:id'])->orderBy('id')->get()->map(fn (BusRoute $r) => $this->present($r))
        );
    }

    public function preview(RoutePointsRequest $request, OsrmClient $osrm): JsonResponse
    {
        $routed = $osrm->route($request->points());

        return response()->json([
            'path' => ['type' => 'LineString', 'coordinates' => $routed['path']],
            'stops' => array_map(fn (array $s) => ['lat' => $s[0], 'lng' => $s[1]], $routed['stops']),
            'snapped' => $routed['snapped'],
            'district_ids' => RouteDistricts::serving(District::all(), $routed['stops'], config('simulation.stop_access_radius_m')),
        ]);
    }

    public function store(StoreRouteRequest $request, OsrmClient $osrm): JsonResponse
    {
        if (BusRoute::where('key', 'like', 'u-%')->count() >= (config('simulation.max_user_routes') ?? 200)) {
            return response()->json(['message' => 'Достигнут лимит нарисованных маршрутов.'], 422);
        }
        $route = RouteWriter::save('u-'.Str::lower(Str::random(8)), $request->validated('name'), $osrm->route($request->points()));

        return response()->json($this->present($route), 201);
    }

    private function present(BusRoute $route): array
    {
        return [
            'id' => $route->id,
            'key' => $route->key,
            'name' => $route->name,
            'path' => $route->path,
            'stops' => $route->stops->map(fn ($s) => ['position' => $s->position, 'lat' => $s->lat, 'lng' => $s->lng])->all(),
            'district_ids' => $route->districts->pluck('id')->all(),
        ];
    }
}
