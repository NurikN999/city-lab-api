<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreComplaintRequest extends FormRequest
{
    public const CATEGORIES = ['transport', 'climate', 'water', 'social', 'other'];

    public function rules(): array
    {
        return [
            'district_id' => ['required', 'integer', 'exists:districts,id'],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'text' => ['required', 'string', 'min:5', 'max:280'],
        ];
    }

    public function messages(): array
    {
        return [
            'district_id.exists' => 'Такого района нет.',
            'category.in' => 'Выберите категорию.',
            'text.required' => 'Опишите проблему.',
            'text.min' => 'Опишите проблему чуть подробнее.',
            'text.max' => 'Не больше 280 символов.',
        ];
    }
}
