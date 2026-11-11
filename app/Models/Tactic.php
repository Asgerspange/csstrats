<?php

namespace App\Models;

use App\Traits\ReleasableTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\{
    BelongsTo,
    HasOne
};

/**
 * App\Models\Tactic
 * @property int $id
 * @property string $team_name
 * @property array|null $team
 * @property array|null $player_descriptions
 * @property array|null $player_loadouts
 * @property array|null $player_timestamps
 * @property string $type
 * @property string $description
 * @property array|null $tags
 * @property string $map
 * @property string $side
 * @property string|null $video
 * @property int $count
 * @property bool $is_released
 * @property \Illuminate\Support\Carbon|null $release_date
 * @property \Illuminate\Support\Carbon|null $matchdate
 * @property string|null $matchlink
 * @property int $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Tactic extends Model
{
    use SoftDeletes, ReleasableTrait; 
    protected $connection = 'mysql';

    protected $table = 'tactics';

    protected $fillable = [
        'team_name',
        'team',
        'player_descriptions',
        'player_loadouts',
        'player_timestamps',
        'type',
        'description',
        'tags',
        'map',
        'side',
        'video',
        'count',
        'is_released',
        'is_featured',
        'release_date',
        'matchdate',
        'matchlink',
        'created_by',
        'created_at',
    ];

    public $casts = [
        'team' => 'array',
        'player_descriptions' => 'array',
        'player_loadouts' => 'array',
        'player_timestamps' => 'array',
        'tags' => 'array',
        'is_released' => 'boolean',
        'is_featured' => 'boolean',
        'release_date' => 'datetime',
    ];

    protected $hidden = [
        'updated_at', 'deleted_at'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function demo(): HasOne
    {
        return $this->hasOne(AdminDemoTactic::class, 'tactic_id', 'id');
    }
}
