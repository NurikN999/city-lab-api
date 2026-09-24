<?php

namespace App\Http\Requests;

class StoreRouteRequest extends RoutePointsRequest
{
    public function rules(): array
    {
        return parent::rules() + ['name' => ['required', 'string', 'max:60']];
    }
}
