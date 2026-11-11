<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MapCalloutZone extends Model
{
    protected $table = 'map_callout_zones';

    protected $fillable = [
        'map_id',
        'layer_id',
        'canonical_name',
        'short_code',
        'zone_type',
        'polygon',
        'color',
        'sort_order',
    ];

    protected $casts = [
        'polygon' => 'array',
    ];

    const TYPE_PRIORITY = [
        'area'  => 0,
        'zone'  => 1,
        'point' => 2,
    ];

    public function map(): BelongsTo
    {
        return $this->belongsTo(Map::class);
    }

    public function layer(): BelongsTo
    {
        return $this->belongsTo(CalloutLayer::class, 'layer_id');
    }

    public function teamNames(): HasMany
    {
        return $this->hasMany(TeamCalloutName::class, 'zone_id');
    }

    /**
     * Priority score for auto-tagging when a player occupies multiple zones.
     * Point (2) > Zone (1) > Area (0), then layer order, then sort_order.
     */
    public function autoTagPriority(): int
    {
        $typePriority = self::TYPE_PRIORITY[$this->zone_type] ?? 0;
        $layerOrder   = $this->layer?->order ?? 0;
        return ($typePriority * 1000) + ($layerOrder * 10) + ($this->sort_order ?? 0);
    }
}