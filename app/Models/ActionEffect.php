<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionEffect extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['delta_pct' => 'float', 'spill' => 'float'];
    }

    public function metric(): BelongsTo
    {
        return $this->belongsTo(Metric::class);
    }
}
