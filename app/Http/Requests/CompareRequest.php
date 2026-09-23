<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompareRequest extends FormRequest
{
    public function rules(): array
    {
        return ['ids' => ['required', 'string', 'regex:/^\d+(,\d+){0,2}$/']];
    }

    /** @return list<int> */
    public function ids(): array
    {
        return array_map('intval', explode(',', $this->validated('ids')));
    }
}
