<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpponentCounterStrat extends Model
{
    use SoftDeletes;

    protected $table = 'opponent_counter_strats';

    protected $fillable = [
        'opponent_team_id', 'team_id', 'name', 'map_id',
        'side', 'description', 'tags', 'zone_ids',
        'demo_id', 'demo_round', 'created_by', 'ai_generated',
    ];

    protected $casts = [
        'tags'         => 'array',
        'zone_ids'     => 'array',
        'ai_generated' => 'boolean',
    ];

    public function opponentTeam(): BelongsTo
    {
        return $this->belongsTo(OpponentTeam::class, 'opponent_team_id');
    }

    public function map(): BelongsTo
    {
        return $this->belongsTo(Map::class, 'map_id');
    }

    public function demo(): BelongsTo
    {
        return $this->belongsTo(Demo::class, 'demo_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}