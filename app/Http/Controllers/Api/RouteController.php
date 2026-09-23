<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BusRoute;
use Illuminate\Http\JsonResponse;

class RouteController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(BusRoute::with(['stops', 'districts:id'])->orderBy('id')->get()->map(fn (BusRoute $r) => [
            'id' => $r->id,
            'key' => $r->key,
            'name' => $r->name,
            'path' => $r->path,
            'stops' => $r->stops->map(fn ($s) => ['position' => $s->position, 'lat' => $s->lat, 'lng' => $s->lng])->all(),
            'district_ids' => $r->districts->pluck('id')->all(),
        ]));
    }
}
