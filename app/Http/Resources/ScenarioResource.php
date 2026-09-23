<?php

namespace App\Http\Resources;

use App\Models\ScenarioItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Scenario */
class ScenarioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'source' => $this->source,
            'budget' => $this->budget,
            'district_id' => $this->district_id,
            'cost' => $this->items->sum(fn (ScenarioItem $i) => $i->action->cost * $i->quantity),
            'items' => $this->items->map(fn (ScenarioItem $i) => [
                'id' => $i->id,
                'action_id' => $i->action_id,
                'action_key' => $i->action->key,
                'action_name' => $i->action->name,
                'district_id' => $i->district_id,
                'route_id' => $i->route_id,
                'quantity' => $i->quantity,
            ])->all(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
