<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string|null $description
 * @property int|null $timestamp
 * @property array|null $loadout
 */
class ProPlayer extends Model
{
    use SoftDeletes;
    protected $table = 'pro_players';

    protected $fillable = [
        'team_id',
        'name',
        'description',
        'timestamp',
        'loadout',
    ];

    protected $casts = [
        'loadout' => 'array',
    ];

    public $timestamps = false;

    public function team()
    {
        return $this->hasOne(ProTeam::class, 'id', 'team_id');
    }
}
