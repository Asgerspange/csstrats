<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CalloutLayer extends Model
{
    protected $table = 'callout_layers';

    protected $fillable = [
        'map_id',
        'name',
        'order',
        'color',
    ];

    public function map(): BelongsTo
    {
        return $this->belongsTo(Map::class);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(MapCalloutZone::class, 'layer_id');
    }
}