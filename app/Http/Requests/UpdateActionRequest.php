<?php

namespace App\Http\Requests;

use App\Models\Metric;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateActionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'cost' => ['required', 'integer', 'min:0', 'max:10000000000'],
            'effects' => ['present', 'array', 'max:10'],
            'effects.*.metric' => ['required', 'string', 'distinct', 'exists:metrics,key'],
            'effects.*.delta_pct' => ['required', 'numeric', 'between:-100,100'],
            'effects.*.spill' => ['required', 'numeric', 'between:0,1'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $computed = Metric::all()->where('is_computed', true)->pluck('key')->all();
            foreach ((array) $this->input('effects', []) as $i => $effect) {
                if (in_array($effect['metric'] ?? null, $computed, true)) {
                    $validator->errors()->add("effects.$i.metric", 'Эта метрика вычисляется автоматически.');
                }
            }
        }];
    }
}
