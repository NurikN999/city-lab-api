<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Scenario extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['budget' => 'integer'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ScenarioItem::class);
    }
}
