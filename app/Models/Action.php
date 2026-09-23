<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Action extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['cost' => 'integer'];
    }

    public function sphere(): BelongsTo
    {
        return $this->belongsTo(Sphere::class);
    }

    public function effects(): HasMany
    {
        return $this->hasMany(ActionEffect::class);
    }
}
