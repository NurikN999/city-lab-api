<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BusRoute extends Model
{
    protected $table = 'routes';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['path' => 'array'];
    }

    public function stops(): HasMany
    {
        return $this->hasMany(RouteStop::class, 'route_id')->orderBy('position');
    }

    public function districts(): BelongsToMany
    {
        return $this->belongsToMany(District::class, 'district_route', 'route_id', 'district_id');
    }
}
