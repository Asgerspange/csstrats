<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $stratbook_name
 * @property int $team_id
 * @property int $created_by
 * @property int|null $map_id
 */
class Stratbook extends Model
{
    use SoftDeletes;

    protected $table = 'stratbooks';

    protected $fillable = [
        'id',
        'stratbook_name',
        'team_id',
        'created_by',
        'map_id',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    public function tactics(): HasMany
    {
        return $this->hasMany(StratbookTactic::class, 'stratbook_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function map(): BelongsTo
    {
        return $this->belongsTo(Map::class, 'map_id');
    }
}
