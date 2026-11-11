<?php
// app/Models/TeamCalloutName.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamCalloutName extends Model
{
    protected $table = 'team_callout_names';

    protected $fillable = [
        'team_id',
        'zone_id',
        'custom_name',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(MapCalloutZone::class, 'zone_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}