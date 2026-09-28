<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScenarioItem extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'lat' => 'float', 'lng' => 'float', 'geometry' => 'array', 'osm_id' => 'integer'];
    }

    public function action(): BelongsTo
    {
        return $this->belongsTo(Action::class);
    }
}
