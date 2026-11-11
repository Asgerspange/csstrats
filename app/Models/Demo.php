<?php

namespace App\Models;

use App\Events;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\{
    Model,
    SoftDeletes,
};
use Illuminate\Database\Eloquent\Relations\{
    BelongsTo,
    HasMany,
    HasOne,
    BelongsToMany
};

class Demo extends Model
{
    use HasFactory, SoftDeletes;
 /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql';
    protected $table = 'demos';

    protected $fillable = [
        'upload_id',
        'name',
        'chunk_index',
        'owner',
        'map',
        'meta',
        'visible_to',
        'created_at',
        'version',
        'is_admin',
        'legacy_admin_id',
    ];

    protected $casts = [
        'visible_to' => 'array',
        'meta' => 'array',
        'is_admin' => 'boolean',
    ];

    protected $appends = ['rounds_count'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner');
    }

    public function mapMetadata(): HasOne
    {
        return $this->hasOne(MapMetadata::class, 'name', 'map');
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(DemoRound::class, 'demo_id');
    }

    public function stats(): HasOne
    {
        return $this->hasOne(DemoStat::class, 'demo_id');
    }

    public function lastRound(): HasOne
    {
        return $this->hasOne(DemoRound::class, 'demo_id')->latestOfMany();
    }

    public function heatmap(): HasOne
    {
        return $this->hasOne(DemoHeatmap::class, 'demo_id');
    }

    public function utility(): HasMany
    {
        return $this->hasMany(DemoUtility::class, 'demo_id');
    }

    public function getRoundsCountAttribute(): int
    {
        if ($this->relationLoaded('rounds')) {
            return $this->rounds->count();
        }

        return $this->rounds()->count();
    }

    protected $dispatchesEvents = [
        'deleted' => Events\DemoDeleted::class,
    ];

    public function folder(): BelongsToMany
    {
        return $this->belongsToMany(DemoFolder::class, 'demo_folder_has_demo', 'demo_id', 'demo_folder_id');
    }

    public function scopeNotInFolder($query)
    {
        return $query->whereDoesntHave('folder');
    }

    public function scopeWithRoundsCount($query)
    {
        return $query->withCount('rounds');
    }

    public function sharedTeams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_demos', 'demo_id', 'team_id');
    }

    /**
     * Readable for chunk/round data: curated admin demos are readable by any
     * authenticated user; personal demos only by their owner or a team they're
     * shared with.
     */
    public function scopeViewableOrCurated($query, int $userId, array $teamIds)
    {
        return $query->where(function ($q) use ($userId, $teamIds) {
            $q->where('is_admin', true)
              ->orWhere('owner', $userId)
              ->orWhereHas('sharedTeams', function ($sq) use ($teamIds) {
                  $sq->whereIn('teams.id', $teamIds);
              });
        });
    }
}
