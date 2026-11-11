<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpponentTeamPlayer extends Model
{
    protected $table = 'opponent_team_players';

    protected $fillable = [
        'opponent_team_id',
        'steamid',
        'name',
        'alias',
        'notes',
        'tendency_cache',
    ];

    protected $casts = [
        'tendency_cache' => 'array',
    ];

    public function opponentTeam(): BelongsTo
    {
        return $this->belongsTo(OpponentTeam::class, 'opponent_team_id');
    }
}