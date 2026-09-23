<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MetricCoupling;
use Illuminate\Http\JsonResponse;

class ModelController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'couplings' => MetricCoupling::with(['source', 'target'])->orderBy('id')->get()->map(fn (MetricCoupling $c) => [
                'source' => $c->source->key, 'target' => $c->target->key, 'factor' => $c->factor,
            ]),
            'constants' => config('simulation'),
        ]);
    }
}
