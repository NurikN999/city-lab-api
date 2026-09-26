<?php

namespace App\Http\Requests;

/** Те же правила, что у сохранения, но название не нужно: предпросмотр ничего не пишет в базу. */
class PreviewScenarioRequest extends StoreScenarioRequest
{
    public function rules(): array
    {
        return ['name' => ['nullable', 'string', 'max:120']] + parent::rules();
    }
}
