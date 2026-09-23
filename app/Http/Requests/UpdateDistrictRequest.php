<?php

namespace App\Http\Requests;

use App\Models\Metric;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDistrictRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'population' => ['sometimes', 'integer', 'min:0', 'max:2000000'],
            'values' => ['sometimes', 'array'],
            'values.*' => ['numeric'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $metrics = Metric::all()->keyBy('key');
            foreach ((array) $this->input('values', []) as $key => $value) {
                $metric = $metrics[$key] ?? null;
                if ($metric === null) {
                    $validator->errors()->add("values.$key", 'Неизвестная метрика.');
                } elseif ($metric->is_computed) {
                    $validator->errors()->add("values.$key", 'Эта метрика вычисляется автоматически.');
                } elseif (is_numeric($value) && ($value < $metric->min_value || $value > $metric->max_value)) {
                    $validator->errors()->add("values.$key", "Значение должно быть от {$metric->min_value} до {$metric->max_value}.");
                }
            }
        }];
    }
}
