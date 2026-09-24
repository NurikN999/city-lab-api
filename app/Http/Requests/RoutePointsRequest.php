<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RoutePointsRequest extends FormRequest
{
    public function rules(): array
    {
        $box = config('simulation.aktau_bbox');

        return [
            'points' => ['required', 'array', 'min:2', 'max:25'],
            'points.*.lat' => ['required', 'numeric', "between:{$box['south']},{$box['north']}"],
            'points.*.lng' => ['required', 'numeric', "between:{$box['west']},{$box['east']}"],
        ];
    }

    public function messages(): array
    {
        return [
            'points.*.lat.between' => 'Точка вне Актау.',
            'points.*.lng.between' => 'Точка вне Актау.',
            'points.min' => 'Нужно минимум две точки.',
        ];
    }

    /** @return list<array{0: float, 1: float}> [lat, lng] */
    public function points(): array
    {
        return array_map(fn (array $p) => [(float) $p['lat'], (float) $p['lng']], $this->validated('points'));
    }
}
