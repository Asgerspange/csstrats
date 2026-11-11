<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StratbookTactic extends Model
{
    use SoftDeletes;

    protected $table = 'stratbook_tactics';

    protected $fillable = [
        'team',
        'name',
        'player_descriptions',
        'player_loadouts',
        'player_timestamps',
        'type',
        'stratbook_id',
        'description',
        'tags',
        'map',
        'side',
        'video',
        'matchdate',
        'matchlink',
        'tactic_id',
        'sort_order',
    ];

    public $casts = [
        'team' => 'array',
        'player_descriptions' => 'array',
        'player_loadouts' => 'array',
        'player_timestamps' => 'array',
        'tags' => 'array'
    ];

    protected $hidden = [
        'deleted_at'
    ];

    public function demo(): HasOne
    {
        return $this->hasOne(AdminDemoTactic::class, 'stratbook_tactic_id', 'id');
    }

    public function roundPlan(): HasOne
    {
        return $this->hasOne(RoundPlan::class, 'stratbook_tactic_id', 'id');
    }
    
    public function board(): HasOne
    {
        return $this->hasOne(Board::class, 'tactic_id', 'id');
    }

    public function headTeam(): HasOneThrough
    {
        return $this->hasOneThrough(Team::class, Stratbook::class, 'id', 'id', 'stratbook_id', 'team_id');
    }

    public function tactic(): BelongsTo
    {
        return $this->belongsTo(Tactic::class, 'tactic_id', 'id');
    }

    public function stratbook(): BelongsTo
    {
        return $this->belongsTo(Stratbook::class, 'stratbook_id', 'id');
    }

    public function snapshot(): HasOne
    {
        return $this->hasOne(BoardSnapshot::class, 'tactic_id', 'id');
    }
}