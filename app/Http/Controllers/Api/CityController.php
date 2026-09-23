<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\Metric;
use App\Models\Sphere;
use App\Simulation\CityStateRepository;
use App\Simulation\SimulationService;
use Illuminate\Http\JsonResponse;

class CityController extends Controller
{
    public function __invoke(CityStateRepository $repo, SimulationService $simulation): JsonResponse
    {
        $state = $simulation->simulate($repo->load(), [], PHP_INT_MAX)->toArray()['before'];

        return response()->json([
            'spheres' => Sphere::orderBy('id')->get(['key', 'name']),
            'metrics' => Metric::with('sphere')->orderBy('id')->get()->map(fn (Metric $m) => [
                'key' => $m->key, 'name' => $m->name, 'unit' => $m->unit, 'sphere' => $m->sphere->key,
                'lower_is_better' => $m->lower_is_better, 'min' => $m->min_value, 'max' => $m->max_value,
                'is_computed' => $m->is_computed,
            ]),
            'districts' => District::orderBy('id')->get()->map(fn (District $d) => [
                'id' => $d->id,
                'name' => $d->name,
                'population' => $d->population,
                'center' => ['lat' => $d->center_lat, 'lng' => $d->center_lng],
                'boundary' => $d->boundary,
                'values' => $state['districts'][$d->id] ?? [],
            ]),
            'city' => $state['city'],
        ]);
    }
}
