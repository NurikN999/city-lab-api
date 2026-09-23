<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class District extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'boundary' => 'array',
            'population' => 'integer',
            'center_lat' => 'float',
            'center_lng' => 'float',
        ];
    }

    public function metrics(): BelongsToMany
    {
        return $this->belongsToMany(Metric::class, 'district_metric_values')->withPivot('value');
    }
}
