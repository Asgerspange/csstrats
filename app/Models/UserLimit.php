<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLimit extends Model
{
    protected $table = 'user_limits';

    protected $fillable = [
        'user_id',
        'max_members',
        'max_teams',
        'max_stratbooks',
        'max_demos',
        'ai_antistrat',
        'round_planner',
        'paddle_subscription_id',
        'paddle_price_id',
    ];

    protected $casts = [
        'max_members' => 'integer',
        'max_teams' => 'integer',
        'max_stratbooks' => 'integer',
        'max_demos' => 'integer',
        'ai_antistrat' => 'boolean',
        'round_planner' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
