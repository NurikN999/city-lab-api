<?php

namespace App\Http\Requests;

use App\Models\Action;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreScenarioRequest extends FormRequest
{
    public function rules(): array
    {
        $box = config('simulation.aktau_bbox');

        return [
            'name' => ['required', 'string', 'max:120'],
            'budget' => ['nullable', 'integer', 'min:1', 'max:10000000000'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.action_id' => ['required', 'integer', 'exists:actions,id'],
            'items.*.district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'items.*.route_id' => ['nullable', 'integer', 'exists:routes,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:3'],
            'complaint_ids' => ['nullable', 'array', 'max:10'], // жалобы, которые решает сценарий
            'complaint_ids.*' => ['integer', 'exists:complaints,id'],
            'items.*.geometry' => ['nullable', 'array'], // расширение дороги
            'items.*.geometry.type' => ['required_with:items.*.geometry', 'in:MultiLineString'],
            'items.*.geometry.coordinates' => ['required_with:items.*.geometry', 'array', 'min:1', 'max:300'], // кусков улицы из тайлов; точек — до 2000
            'items.*.osm_id' => ['nullable', 'integer', 'min:1'], // снос здания
            'items.*.lat' => ['nullable', 'numeric', "between:{$box['south']},{$box['north']}"],
            'items.*.lng' => ['nullable', 'numeric', "between:{$box['west']},{$box['east']}"],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.lat.between' => 'Объект вне Актау.',
            'items.*.lng.between' => 'Объект вне Актау.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $items = (array) $this->input('items', []);
            $scopes = Action::whereIn('id', collect($items)->pluck('action_id')->filter())->pluck('scope', 'id');
            foreach ($items as $i => $item) {
                $scope = $scopes[$item['action_id'] ?? 0] ?? null;
                if ($scope === 'district' && (empty($item['district_id']) || ! empty($item['route_id']))) {
                    $validator->errors()->add("items.$i.district_id", 'Для этого действия нужен район и не нужен маршрут.');
                }
                if ($scope === 'route' && (empty($item['route_id']) || ! empty($item['district_id']))) {
                    $validator->errors()->add("items.$i.route_id", 'Для этого действия нужен маршрут и не нужен район.');
                }
                if ($scope === 'point' && (! isset($item['lat'], $item['lng']) || ! empty($item['district_id']) || ! empty($item['route_id']))) {
                    $validator->errors()->add("items.$i.lat", 'Объект ставится на карту: нужны координаты, район и маршрут не нужны.');
                }
                if ($scope === 'line' && ! $this->lineInsideAktau($item['geometry']['coordinates'] ?? null)) {
                    $validator->errors()->add("items.$i.geometry", 'Нужна линия улицы в пределах Актау (до 2000 точек).');
                }
                if ($scope === 'building' && (empty($item['osm_id']) || ! isset($item['lat'], $item['lng']))) {
                    $validator->errors()->add("items.$i.osm_id", 'Для сноса нужны здание (osm_id) и его координаты.');
                }
            }
        }];
    }

    /** MultiLineString [[[lng, lat], …], …]: каждая линия от 2 точек, всего до 2000, все внутри Актау. */
    private function lineInsideAktau(mixed $lines): bool
    {
        if (! is_array($lines) || $lines === []) {
            return false;
        }
        $box = config('simulation.aktau_bbox');
        $points = 0;
        foreach ($lines as $line) {
            if (! is_array($line) || count($line) < 2) {
                return false;
            }
            foreach ($line as $p) {
                if (! is_array($p) || count($p) !== 2 || ! is_numeric($p[0]) || ! is_numeric($p[1])
                    || $p[0] < $box['west'] || $p[0] > $box['east'] || $p[1] < $box['south'] || $p[1] > $box['north']) {
                    return false;
                }
                $points++;
            }
        }

        return $points <= 2000;
    }
}
