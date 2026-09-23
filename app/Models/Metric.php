<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Metric extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'lower_is_better' => 'boolean',
            'is_computed' => 'boolean',
            'min_value' => 'float',
            'max_value' => 'float',
            'satisfaction_weight' => 'float',
        ];
    }

    public function sphere(): BelongsTo
    {
        return $this->belongsTo(Sphere::class);
    }
}
