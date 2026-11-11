<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OpponentTeam extends Model
{
    use SoftDeletes;

    protected $table = 'opponent_teams';

    protected $fillable = [
        'user_id',
        'name',
        'tag',
        'notes',
        'platform_url',
        'ai_insights',
        'insights_updated_at',
        'insights_status',
        'insights_error',
    ];

    protected $casts = [
        'ai_insights' => 'array',
        'insights_updated_at' => 'datetime',
    ];

    // ─── Relations ────────────────────────────────────────────────────────────

    /**
     * The user who owns this scouting profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Teams the owner has shared this profile with.
     */
    public function sharedTeams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'opponent_team_shares', 'opponent_team_id', 'team_id')
            ->withTimestamps();
    }

    public function demos(): BelongsToMany
    {
        return $this->belongsToMany(Demo::class, 'opponent_team_demos', 'opponent_team_id', 'demo_id')
            ->withTimestamps();
    }

    public function players(): HasMany
    {
        return $this->hasMany(OpponentTeamPlayer::class, 'opponent_team_id');
    }

    public function counterStrats(): HasMany
    {
        return $this->hasMany(OpponentCounterStrat::class, 'opponent_team_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    /**
     * Profiles a user can see: the ones they own, plus any shared with a team
     * they belong to.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $teamIds = $user->teams->pluck('id')->all();

        return $query->where(function (Builder $q) use ($user, $teamIds) {
            $q->where('user_id', $user->id);

            if (! empty($teamIds)) {
                $q->orWhereHas('sharedTeams', fn (Builder $s) => $s->whereIn('teams.id', $teamIds));
            }
        });
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Return all known steamids for this opponent, used for fuzzy demo matching.
     */
    public function steamIds(): array
    {
        return $this->players()->pluck('steamid')->toArray();
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->user_id === (int) $user->id;
    }
}
