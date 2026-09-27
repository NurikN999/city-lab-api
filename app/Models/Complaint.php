<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Complaint extends Model
{
    /** Жалобы, которые горят на карте */
    public const ACTIVE = ['new', 'accepted'];

    protected $guarded = [];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    public function sphere(): BelongsTo
    {
        return $this->belongsTo(Sphere::class);
    }

    /** @return array<string, mixed> */
    public function toFeed(): array
    {
        return [
            'id' => $this->id,
            'district_id' => $this->district_id,
            'category' => $this->sphere?->key ?? 'other',
            'text' => $this->text,
            'status' => $this->status,
            'scenario_id' => $this->scenario_id,
            'scenario_name' => $this->scenario?->name,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
