<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Nade extends Model
{
    protected $fillable = [
        'map_id',
        'type',
        'side',
        'name',
        'description',
        'landing_x',
        'landing_y',
        'active',
    ];

    protected $casts = [
        'landing_x' => 'decimal:2',
        'landing_y' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function map(): BelongsTo
    {
        return $this->belongsTo(Map::class);
    }

    public function throwPoints(): HasMany
    {
        return $this->hasMany(ThrowPoint::class)->orderBy('order');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeForSide($query, string $side)
    {
        return $query->whereIn('side', [$side, 'both']);
    }
}