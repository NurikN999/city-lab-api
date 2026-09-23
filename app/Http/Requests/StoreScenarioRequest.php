<?php

namespace App\Http\Requests;

use App\Models\Action;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreScenarioRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'budget' => ['nullable', 'integer', 'min:1', 'max:10000000000'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.action_id' => ['required', 'integer', 'exists:actions,id'],
            'items.*.district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'items.*.route_id' => ['nullable', 'integer', 'exists:routes,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:3'],
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
            }
        }];
    }
}
