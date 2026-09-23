<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MetricCoupling extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['factor' => 'float'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Metric::class, 'source_metric_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Metric::class, 'target_metric_id');
    }
}
