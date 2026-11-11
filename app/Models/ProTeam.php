<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProTeam extends Model
{
    use SoftDeletes;

    protected $table = 'pro_teams';

    protected $fillable = [
        'id',
        'name',
    ];

    public function players(): HasMany
    {
        return $this->hasMany(ProPlayer::class, 'team_id', 'id');
    }
}
