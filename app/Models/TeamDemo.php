<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamDemo extends Model
{
    protected $table = 'team_demos';
    protected $fillable = [
        'team_id',
        'demo_id',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function demo(): BelongsTo
    {
        return $this->belongsTo(Demo::class);
    }
}
