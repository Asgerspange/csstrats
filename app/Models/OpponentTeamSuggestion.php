<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpponentTeamSuggestion extends Model
{
    protected $table = 'opponent_team_suggestions';

    protected $fillable = [
        'user_id', 'demo_id', 'clan_name',
        'steamids', 'player_names', 'map', 'dismissed',
    ];

    protected $casts = [
        'steamids' => 'array',
        'player_names' => 'array',
        'dismissed' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function demo(): BelongsTo
    {
        return $this->belongsTo(Demo::class, 'demo_id');
    }
}
