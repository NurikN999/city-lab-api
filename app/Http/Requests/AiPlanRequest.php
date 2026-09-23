<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiPlanRequest extends FormRequest
{
    public function rules(): array
    {
        return ['prompt' => ['required', 'string', 'min:5', 'max:500']];
    }
}
